<?php

use App\DTOs\Payment\RecordPaymentData;
use App\Enums\BookingStatus;
use App\Enums\NotificationEvent;
use App\Enums\NotificationStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Mail\NotificationMail;
use App\Models\Booking;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Partner;
use App\Models\Setting;
use App\Models\Trip;
use App\Services\CancellationService;
use App\Services\Notifications\NotificationService;
use App\Services\PartnerService;
use App\Services\PaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    seedCurrencies();
    Setting::factory()->create(['key' => 'notifications.admin_emails', 'value' => ['ops@sharmstation.test', 'boss@sharmstation.test']]);
    Setting::factory()->create(['key' => 'site.name', 'value' => 'Sharm Station Viaggi']);
});

function notifiedBooking(array $attributes = []): Booking
{
    $trip = Trip::factory()->create(['title' => ['en' => 'Ras Mohammed snorkelling', 'it' => 'Snorkeling a Ras Mohammed']]);

    return Booking::factory()->create(['bookable_id' => $trip->id, 'lead_email' => 'maria@example.com', 'lead_name' => 'Maria', 'total' => 100, 'deposit_amount' => 30, ...$attributes]);
}

test('a new booking emails the customer in the booking language and every admin', function () {
    Mail::fake();
    $snorkelling = snorkellingTrip();

    $this->postJson('/api/v1/bookings', [
        'trip_slug' => $snorkelling->slug, 'booking_type' => 'group', 'travel_date' => now()->addDays(10)->toDateString(),
        'adults' => 1, 'children' => 0, 'nationality_code' => 'IT',
        'lead' => ['name' => 'Maria Rossi', 'email' => 'maria@example.com', 'phone' => '+39 333 1234567'],
        'travelers' => [['first_name' => 'Maria', 'last_name' => 'Rossi', 'type' => 'adult']],
    ], ['Accept-Language' => 'it'])->assertCreated();

    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->event === NotificationEvent::BookingCreated
        && $mail->hasTo('maria@example.com') && $mail->mailLocale === 'it' && str_contains($mail->content->subject, 'ricevuta'));
    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->event === NotificationEvent::BookingCreatedAdmin && $mail->hasTo('ops@sharmstation.test'));
    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->event === NotificationEvent::BookingCreatedAdmin && $mail->hasTo('boss@sharmstation.test'));

    expect(NotificationLog::query()->where('status', NotificationStatus::Queued)->count())->toBe(3);
});

test('the stored template is used, placeholder values are escaped and the log row is marked sent', function () {
    NotificationTemplate::query()->create([
        'event' => NotificationEvent::ProofRejected, 'locale' => 'en', 'is_enabled' => true,
        'subject' => 'Proof for {{reference}} declined', 'body' => "Dear {{customer_name}},\n\n**Reason:** {{reason}}",
    ]);
    $booking = notifiedBooking(['lead_name' => '<script>alert(1)</script>']);

    app(NotificationService::class)->booking(NotificationEvent::ProofRejected, $booking, ['reason' => 'Blurry <b>photo</b>']);

    $log = NotificationLog::query()->sole();
    expect($log->status)->toBe(NotificationStatus::Sent)
        ->and($log->subject)->toBe("Proof for {$booking->reference} declined")
        ->and($log->sent_at)->not->toBeNull();

    $html = (new NotificationMail(NotificationEvent::ProofRejected, app(NotificationService::class)->preview(NotificationEvent::ProofRejected, 'en'), 'en'))->render();
    expect($html)->toContain('<strong>Reason:</strong>');

    $sent = app(NotificationService::class);
    $rendered = (fn () => $this->renderer->render(NotificationEvent::ProofRejected, 'en', 'x', 'Dear {{customer_name}}', ['customer_name' => '<script>alert(1)</script>']))->call($sent);
    expect($rendered->html)->not->toContain('<script>')->toContain('&lt;script&gt;');
});

test('a disabled template sends nothing and a dedupe key prevents a second email', function () {
    Mail::fake();
    $booking = notifiedBooking();
    NotificationTemplate::query()->create(['event' => NotificationEvent::PaymentFailed, 'locale' => 'en', 'subject' => 'x', 'body' => 'y', 'is_enabled' => false]);

    app(NotificationService::class)->booking(NotificationEvent::PaymentFailed, $booking);
    app(NotificationService::class)->booking(NotificationEvent::BalanceReminder, $booking, dedupeKey: 'balance_reminder:'.$booking->id);
    app(NotificationService::class)->booking(NotificationEvent::BalanceReminder, $booking, dedupeKey: 'balance_reminder:'.$booking->id);

    Mail::assertQueuedCount(1);
    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->event === NotificationEvent::BalanceReminder);
});

test('the deposit confirmation carries the voucher PDF and the balance closes the account with a second email', function () {
    Mail::fake();
    $booking = notifiedBooking(['status' => BookingStatus::AwaitingPayment]);
    $admin = staffUser();

    app(PaymentService::class)->recordPayment($booking, new RecordPaymentData(30, CarbonImmutable::now(), type: PaymentType::Deposit), $admin);
    app(PaymentService::class)->recordPayment($booking->fresh(), new RecordPaymentData(70, CarbonImmutable::now()), $admin);

    Mail::assertQueued(NotificationMail::class, function (NotificationMail $mail) use ($booking): bool {
        if ($mail->event !== NotificationEvent::DepositConfirmed) {
            return false;
        }

        $attachment = $mail->attachments()[0];

        return $mail->hasTo('maria@example.com') && $attachment->as === "voucher-{$booking->reference}.pdf";
    });
    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->event === NotificationEvent::DepositConfirmedAdmin);
    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->event === NotificationEvent::BalanceRecorded);
    expect($booking->fresh()->payment_status)->toBe(PaymentStatus::FullyPaid);
});

test('partner bookings email the partner account, cancellations and partner decisions notify the right people', function () {
    Mail::fake();
    $partner = Partner::factory()->create();
    $booking = notifiedBooking(['partner_id' => $partner->id, 'status' => BookingStatus::Confirmed]);

    app(CancellationService::class)->request($booking, 'Flight cancelled', null);
    app(CancellationService::class)->decline($booking->fresh(), staffUser(), 'Non-refundable');

    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->event === NotificationEvent::CancellationRequestedAdmin && $mail->hasTo('ops@sharmstation.test'));
    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->event === NotificationEvent::CancellationDeclined && $mail->hasTo($partner->user->email)
        && str_contains($mail->content->text, 'Non-refundable'));

    $applicant = Partner::factory()->pending()->create();
    app(PartnerService::class)->reject($applicant, 'Incomplete documents', staffUser());
    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->event === NotificationEvent::PartnerRejected && $mail->hasTo($applicant->user->email));
});

test('every event renders its default template in both languages', function (NotificationEvent $event) {
    $service = app(NotificationService::class);

    foreach (['en', 'it'] as $locale) {
        $html = $service->previewHtml($event, $locale);
        expect($html)->toContain('Sharm Station Viaggi')->not->toContain('{{');
    }
})->with(NotificationEvent::cases());
