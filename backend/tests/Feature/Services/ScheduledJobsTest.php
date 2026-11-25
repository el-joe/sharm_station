<?php

use App\Enums\BookingStatus;
use App\Enums\NotificationEvent;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\RoomAvailabilityStatus;
use App\Enums\ServiceType;
use App\Mail\NotificationMail;
use App\Models\Booking;
use App\Models\Currency;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    seedCurrencies();
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-12-01 09:00'));
    Setting::factory()->create(['key' => 'notifications.admin_emails', 'value' => ['ops@sharmstation.test']]);
});

function confirmedWithBalance(array $attributes = []): Booking
{
    $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed, 'payment_status' => PaymentStatus::DepositPaid, 'total' => 100, 'deposit_amount' => 30, ...$attributes]);
    Payment::query()->create(['booking_id' => $booking->id, 'type' => PaymentType::Deposit, 'status' => PaymentRecordStatus::Succeeded, 'amount' => 30, 'currency' => 'EUR', 'exchange_rate' => 1, 'amount_in_currency' => 30, 'paid_at' => now()]);

    return $booking;
}

function queuedEvents(): array
{
    return Mail::queued(NotificationMail::class)->map(fn (NotificationMail $mail): string => $mail->event->value)->all();
}

test('balance reminders go out N days before the due date, once', function () {
    $due = confirmedWithBalance(['start_date' => '2026-12-03']);
    confirmedWithBalance(['start_date' => '2026-12-20']);
    $paid = confirmedWithBalance(['start_date' => '2026-12-02', 'balance_due_on' => '2026-12-02']);
    Payment::query()->create(['booking_id' => $paid->id, 'type' => PaymentType::Balance, 'status' => PaymentRecordStatus::Succeeded, 'amount' => 70, 'currency' => 'EUR', 'exchange_rate' => 1, 'amount_in_currency' => 70, 'paid_at' => now()]);

    $this->artisan('bookings:balance-reminders')->assertSuccessful();
    $this->artisan('bookings:balance-reminders')->assertSuccessful();

    Mail::assertQueuedCount(1);
    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->event === NotificationEvent::BalanceReminder && $mail->hasTo($due->lead_email));
});

test('overdue balances are listed to the admin once a day', function () {
    $late = confirmedWithBalance(['start_date' => '2026-11-28']);
    confirmedWithBalance(['start_date' => '2026-12-10']);

    $this->artisan('bookings:overdue-balances')->assertSuccessful();
    $this->artisan('bookings:overdue-balances')->assertSuccessful();

    Mail::assertQueuedCount(1);
    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->event === NotificationEvent::BalanceOverdueAdmin
        && $mail->hasTo('ops@sharmstation.test') && str_contains($mail->content->text, $late->reference));
});

test('accommodation holds are warned before they end, then expire and release the rooms', function () {
    $room = Room::factory()->create();
    $warned = Booking::factory()->create(['service_type' => ServiceType::Accommodation, 'bookable_type' => 'room', 'bookable_id' => $room->id, 'created_at' => now()->subMinutes(25), 'payment_due_at' => now()->addMinutes(5)]);
    $expired = Booking::factory()->create(['service_type' => ServiceType::Accommodation, 'bookable_type' => 'room', 'bookable_id' => $room->id, 'created_at' => now()->subMinutes(40), 'payment_due_at' => now()->subMinutes(10)]);
    RoomAvailability::factory()->create(['room_id' => $room->id, 'date' => '2026-12-10', 'status' => RoomAvailabilityStatus::Held, 'booking_id' => $expired->id, 'held_until' => now()->subMinutes(10)]);
    $inReview = Booking::factory()->create(['service_type' => ServiceType::Accommodation, 'bookable_type' => 'room', 'bookable_id' => $room->id, 'status' => BookingStatus::AwaitingReceiptReview, 'payment_status' => PaymentStatus::AwaitingReceiptReview, 'payment_due_at' => now()->subMinutes(10)]);

    $this->artisan('bookings:process-holds')->assertSuccessful();
    $this->artisan('bookings:process-holds')->assertSuccessful();

    expect($expired->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and(RoomAvailability::query()->where('booking_id', $expired->id)->exists())->toBeFalse()
        ->and($warned->fresh()->status)->toBe(BookingStatus::AwaitingPayment)
        ->and($inReview->fresh()->status)->toBe(BookingStatus::AwaitingReceiptReview);
    expect(queuedEvents())->toEqualCanonicalizing(['hold_expiring', 'booking_expired']);
});

test('unpaid trip bookings expire after the payment deadline with an email', function () {
    $late = Booking::factory()->create(['payment_due_at' => now()->subHour()]);
    $onTime = Booking::factory()->create(['payment_due_at' => now()->addHour()]);

    $this->artisan('bookings:expire-unpaid')->assertSuccessful();
    $this->artisan('bookings:expire-unpaid')->assertSuccessful();

    expect($late->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($late->fresh()->statusLogs()->first()->note)->toBe('Payment deadline expired')
        ->and($onTime->fresh()->status)->toBe(BookingStatus::AwaitingPayment);
    expect(queuedEvents())->toBe(['booking_expired']);
});

test('review invitations use the Google write-review link the day after the service', function () {
    Setting::factory()->create(['key' => 'google_reviews.place_id', 'value' => 'ChIJ_test']);
    $done = Booking::factory()->create(['status' => BookingStatus::FullyPaid, 'start_date' => '2026-11-30']);
    Booking::factory()->create(['status' => BookingStatus::FullyPaid, 'start_date' => '2026-12-01']);
    Booking::factory()->create(['status' => BookingStatus::Cancelled, 'start_date' => '2026-11-30']);

    $this->artisan('reviews:send-invitations')->assertSuccessful();
    $this->artisan('reviews:send-invitations')->assertSuccessful();

    Mail::assertQueuedCount(1);
    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->hasTo($done->lead_email)
        && $mail->content->actionUrl === 'https://search.google.com/local/writereview?placeid=ChIJ_test');
});

test('partners get last month\'s statement on the 1st with the PDF attached, once', function () {
    $partner = Partner::factory()->create();
    $quiet = Partner::factory()->create();
    Booking::factory()->create(['partner_id' => $partner->id, 'partner_total' => 80, 'status' => BookingStatus::Confirmed, 'created_at' => '2026-11-15 10:00']);

    $this->artisan('partners:send-statements')->assertSuccessful();
    $this->artisan('partners:send-statements')->assertSuccessful();

    Mail::assertQueuedCount(1);
    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->event === NotificationEvent::PartnerStatement
        && $mail->hasTo($partner->user->email) && $mail->attachments()[0]->as === 'statement-2026-11.pdf');
    Mail::assertNotQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->hasTo($quiet->user->email));
});

test('exchange rates refresh on the interval, keep manual rates and the margin', function () {
    Http::fake(['open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['EUR' => 1, 'USD' => 1.1, 'EGP' => 55.5]])]);
    Currency::query()->where('code', 'EGP')->update(['is_manual_rate' => true]);
    Setting::factory()->create(['key' => 'currency.refresh_hours', 'value' => 12]);

    $this->artisan('currencies:refresh-rates')->assertSuccessful();

    $usd = Currency::query()->where('code', 'USD')->first();
    $egp = Currency::query()->where('code', 'EGP')->first();
    expect((float) $usd->rate_to_eur)->toBe(1.1)
        ->and((float) $egp->rate_to_eur)->toBe(50.0)
        ->and($egp->effectiveRate())->toBe(51.0);

    $this->artisan('currencies:refresh-rates')->expectsOutput('Refreshed recently.')->assertSuccessful();
    Http::assertSentCount(1);

    $this->travel(13)->hours();
    $this->artisan('currencies:refresh-rates')->assertSuccessful();
    Http::assertSentCount(2);
});
