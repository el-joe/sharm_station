<?php

use App\Enums\BookingStatus;
use App\Enums\CancellationDecision;
use App\Enums\PaymentStatus;
use App\Enums\RoomAvailabilityStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Partner;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\Trip;
use App\Models\User;
use App\Services\CancellationService;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
});

function cancellationUrl(Booking $booking, ?string $token = null): string
{
    return "/api/v1/bookings/{$booking->reference}/cancellation-request".($token ? "?token={$token}" : '');
}

test('the booking link holder requests a cancellation once; asking again changes nothing', function () {
    $booking = Booking::factory()->create();

    $this->postJson(cancellationUrl($booking, $booking->access_token), ['reason' => 'Our flight was cancelled.'])
        ->assertCreated()
        ->assertJsonPath('data.cancellation.status', 'pending')
        ->assertJsonPath('data.cancellation.reason', 'Our flight was cancelled.')
        ->assertJsonPath('data.cancellation.can_request', false);

    $first = $booking->fresh()->cancellation_requested_at;
    expect($first)->not->toBeNull()
        ->and($booking->fresh()->status)->toBe(BookingStatus::AwaitingPayment);
    $this->assertDatabaseHas('booking_status_logs', ['booking_id' => $booking->id, 'note' => 'Cancellation requested: Our flight was cancelled.']);

    $this->travel(1)->hours();
    $this->postJson(cancellationUrl($booking, $booking->access_token), ['reason' => 'Again'])
        ->assertOk()
        ->assertJsonPath('data.cancellation.reason', 'Our flight was cancelled.');
    expect($booking->fresh()->cancellation_requested_at->equalTo($first))->toBeTrue()
        ->and($booking->statusLogs()->count())->toBe(1);
});

test('owners and partners may request; strangers and missing reasons may not', function () {
    $owner = User::factory()->create();
    $partner = Partner::factory()->create();
    $own = Booking::factory()->create(['user_id' => $owner->id]);
    $partnerBooking = Booking::factory()->create(['partner_id' => $partner->id]);

    $this->postJson(cancellationUrl($own), ['reason' => 'Change of plans'])->assertNotFound();
    $this->postJson(cancellationUrl($own, 'wrong-token'), ['reason' => 'Change of plans'])->assertNotFound();

    Sanctum::actingAs(User::factory()->create());
    $this->postJson(cancellationUrl($own), ['reason' => 'Change of plans'])->assertNotFound();

    Sanctum::actingAs($owner);
    $this->postJson(cancellationUrl($own), ['reason' => ''])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->postJson(cancellationUrl($own), ['reason' => 'Change of plans'])->assertCreated();
    expect($own->fresh()->cancellation_requested_by)->toBe($owner->id);

    Sanctum::actingAs($partner->user);
    $this->postJson(cancellationUrl($partnerBooking), ['reason' => 'Client cancelled'])->assertCreated();
});

test('completed or cancelled bookings cannot be cancelled', function (BookingStatus $status) {
    $booking = Booking::factory()->create(['status' => $status]);

    $this->postJson(cancellationUrl($booking, $booking->access_token), ['reason' => 'Too late now'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');
})->with([BookingStatus::Completed, BookingStatus::Cancelled, BookingStatus::Refunded]);

test('the refund suggestion follows the policy time bands on what was paid', function (int $daysBefore, float $refund, string $rule) {
    $booking = Booking::factory()->create([
        'bookable_id' => Trip::factory()->create(['free_cancellation_hours' => null])->id,
        'start_date' => now()->addDays($daysBefore)->toDateString(), 'total' => 200, 'deposit_amount' => 60,
        'payment_status' => PaymentStatus::DepositPaid, 'status' => BookingStatus::Confirmed, 'cancellation_requested_at' => now(), 'cancellation_reason' => 'Ill',
    ]);

    $suggestion = app(CancellationService::class)->suggestRefund($booking);

    expect($suggestion->paid)->toBe(60.0)->and($suggestion->refund)->toBe($refund)->and($suggestion->rule)->toBe($rule);
})->with([
    'more than 7 days' => [10, 60.0, '8 or more days before: full refund'],
    'between 3 and 7 days' => [5, 30.0, '3 to 7 days before: 50% refund'],
    'less than 3 days' => [1, 0.0, 'Less than 3 days before: no refund'],
]);

test('a product free-cancellation window gives a full refund', function () {
    $trip = Trip::factory()->create(['free_cancellation_hours' => 24]);
    $booking = Booking::factory()->create(['bookable_id' => $trip->id, 'start_date' => now()->addDays(2)->toDateString(), 'deposit_amount' => 30,
        'payment_status' => PaymentStatus::DepositPaid, 'cancellation_requested_at' => now()]);

    $suggestion = app(CancellationService::class)->suggestRefund($booking);

    expect($suggestion->refund)->toBe(30.0)->and($suggestion->rule)->toBe('Free cancellation up to 24 hours before');
});

describe('admin decision', function () {
    beforeEach(function () {
        $this->withoutVite();
        $this->admin = staffUser();
        $room = Room::factory()->create();
        $this->booking = Booking::factory()->create([
            'service_type' => ServiceType::Accommodation, 'bookable_type' => 'room', 'bookable_id' => $room->id,
            'start_date' => '2026-12-20', 'check_in' => '2026-12-20', 'check_out' => '2026-12-22', 'total' => 300, 'deposit_amount' => 90,
            'payment_status' => PaymentStatus::DepositPaid, 'status' => BookingStatus::Confirmed,
            'cancellation_requested_at' => now(), 'cancellation_reason' => 'Family emergency',
        ]);
        RoomAvailability::factory()->create(['room_id' => $room->id, 'date' => '2026-12-20', 'status' => RoomAvailabilityStatus::Booked, 'booking_id' => $this->booking->id]);
    });

    test('pending requests are badged and filtered in the list, with the suggestion on the detail page', function () {
        Booking::factory()->create();

        $this->actingAs($this->admin)->getJson(route('admin.bookings.data', ['draw' => 1, 'start' => 0, 'length' => 10, 'cancellation' => 'pending']))
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.status', fn (string $html) => str_contains($html, 'Cancellation requested'));

        $this->actingAs($this->admin)->get(route('admin.bookings.show', $this->booking))
            ->assertOk()->assertSee('Family emergency')->assertSee('8 or more days before: full refund')->assertSee('€90.00');
    });

    test('approving cancels the booking, releases the rooms and stores the refund', function () {
        $this->actingAs($this->admin)->post(route('admin.bookings.cancellation.approve', $this->booking), ['refund_amount' => 90, 'note' => 'Refund by bank transfer'])
            ->assertRedirect(route('admin.bookings.show', $this->booking));

        $booking = $this->booking->fresh();
        expect($booking->status)->toBe(BookingStatus::Cancelled)
            ->and($booking->cancellation_decision)->toBe(CancellationDecision::Approved)
            ->and((float) $booking->refund_amount)->toBe(90.0)
            ->and(RoomAvailability::query()->where('booking_id', $booking->id)->exists())->toBeFalse();
        $this->assertDatabaseHas('booking_status_logs', ['booking_id' => $booking->id, 'to_status' => 'cancelled', 'user_id' => $this->admin->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'cancellation_approved', 'subject_type' => 'booking', 'subject_id' => $booking->id]);

        $this->actingAs($this->admin)->post(route('admin.bookings.cancellation.decline', $booking), ['decline_reason' => 'Too late'])
            ->assertSessionHasErrors('cancellation');
    });

    test('the refund cannot exceed what was paid', function () {
        $this->actingAs($this->admin)->post(route('admin.bookings.cancellation.approve', $this->booking), ['refund_amount' => 150])
            ->assertSessionHasErrors('refund_amount');
        expect($this->booking->fresh()->status)->toBe(BookingStatus::Confirmed);
    });

    test('declining keeps the booking and records the reason', function () {
        $this->actingAs($this->admin)->post(route('admin.bookings.cancellation.decline', $this->booking), ['decline_reason' => ''])
            ->assertSessionHasErrors('decline_reason');
        $this->actingAs($this->admin)->post(route('admin.bookings.cancellation.decline', $this->booking), ['decline_reason' => 'Non-refundable rate'])
            ->assertRedirect();

        $booking = $this->booking->fresh();
        expect($booking->status)->toBe(BookingStatus::Confirmed)
            ->and($booking->cancellation_decision)->toBe(CancellationDecision::Declined)
            ->and($booking->cancellation_decision_note)->toBe('Non-refundable rate');

        $this->getJson("/api/v1/bookings/{$booking->reference}?token={$booking->access_token}")
            ->assertJsonPath('data.cancellation.status', 'declined')
            ->assertJsonPath('data.cancellation.decision_note', 'Non-refundable rate');
    });
});
