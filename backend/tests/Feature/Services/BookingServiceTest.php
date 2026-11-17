<?php

use App\DTOs\Booking\CreateBookingData;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\PriceRule;
use App\Enums\RoomAvailabilityStatus;
use App\Exceptions\DomainValidationException;
use App\Models\Addon;
use App\Models\Booking;
use App\Models\Partner;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\Trip;
use App\Services\BookingService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    $this->service = app(BookingService::class);
});

function buggyBookingPayload(Trip $trip, array $overrides = []): array
{
    return [
        'trip_slug' => $trip->slug,
        'booking_type' => 'group',
        'travel_date' => '2026-11-25',
        'adults' => 3,
        'children' => 0,
        'units' => [
            ['unit_option_id' => $trip->unitOptions[0]->id, 'quantity' => 1],
            ['unit_option_id' => $trip->unitOptions[1]->id, 'quantity' => 1],
        ],
        'nationality_code' => 'IT',
        'lead' => ['name' => 'Giulia Rossi', 'email' => 'giulia@example.com', 'phone' => '+39 333 1234567'],
        'travelers' => [
            ['first_name' => 'Giulia', 'last_name' => 'Rossi', 'type' => 'adult'],
            ['first_name' => 'Paolo', 'last_name' => 'Rossi', 'type' => 'adult'],
            ['first_name' => 'Anna', 'last_name' => 'Rossi', 'type' => 'adult'],
        ],
        ...$overrides,
    ];
}

test('a trip booking is created from the quote with units, add-ons, travellers and a status log', function () {
    $trip = buggyTrip();
    $addon = Addon::factory()->create(['price' => 10, 'pricing_basis' => 'per_booking']);
    $trip->addons()->attach($addon->id);

    $booking = $this->service->createFromQuote(CreateBookingData::fromArray(buggyBookingPayload($trip, [
        'addons' => [['addon_id' => $addon->id]],
    ])));

    expect($booking->reference)->toBe('BK-2041')
        ->and($booking->status)->toBe(BookingStatus::AwaitingPayment)
        ->and($booking->payment_status)->toBe(PaymentStatus::AwaitingPayment)
        ->and((float) $booking->retail_total)->toBe(95.0)
        ->and((float) $booking->total)->toBe(95.0)
        ->and((float) $booking->deposit_amount)->toBe(28.5)
        ->and((float) $booking->balance_amount)->toBe(66.5)
        ->and($booking->start_date->toDateString())->toBe('2026-11-25')
        ->and($booking->bookable->is($trip))->toBeTrue()
        ->and($booking->units)->toHaveCount(2)
        ->and($booking->units->pluck('name')->all())->toBe(['Single buggy', 'Double buggy'])
        ->and($booking->addons)->toHaveCount(1)
        ->and($booking->travelers)->toHaveCount(3)
        ->and($booking->statusLogs()->first()->to_status)->toBe(BookingStatus::AwaitingPayment);
});

test('references are sequential', function () {
    $trip = buggyTrip();

    $first = $this->service->createFromQuote(CreateBookingData::fromArray(buggyBookingPayload($trip)));
    $second = $this->service->createFromQuote(CreateBookingData::fromArray(buggyBookingPayload($trip)));

    expect([$first->reference, $second->reference])->toBe(['BK-2041', 'BK-2042']);
});

test('an invalid quote is not booked', function () {
    $trip = buggyTrip();

    $payload = buggyBookingPayload($trip, ['units' => [['unit_option_id' => $trip->unitOptions[1]->id, 'quantity' => 1]]]);

    expect(fn () => $this->service->createFromQuote(CreateBookingData::fromArray($payload)))
        ->toThrow(DomainValidationException::class, 'The selected units have 2 seats for 3 travellers. Add another unit.');

    expect(Booking::query()->count())->toBe(0);
});

test('the travellers must match the party', function () {
    $trip = buggyTrip();
    $payload = buggyBookingPayload($trip);
    array_pop($payload['travelers']);

    try {
        $this->service->createFromQuote(CreateBookingData::fromArray($payload));
        $this->fail('Expected a validation exception.');
    } catch (DomainValidationException $exception) {
        expect($exception->errors()['travelers'])->toBe(['Enter the details of all 3 travellers.']);
    }
});

test('a partner booking stores retail, partner and commission totals with the commission source', function () {
    $trip = buggyTrip();
    $partner = Partner::factory()->create();

    $booking = $this->service->createFromQuote(CreateBookingData::fromArray(buggyBookingPayload($trip), partner: $partner));

    expect($booking->partner_id)->toBe($partner->id)
        ->and((float) $booking->retail_total)->toBe(85.0)
        ->and((float) $booking->partner_total)->toBe(72.25)
        ->and((float) $booking->commission_amount)->toBe(12.75)
        ->and((float) $booking->commission_percent)->toBe(15.0)
        ->and((float) $booking->total)->toBe(72.25)
        ->and($booking->price_rule)->toBe(PriceRule::GlobalSetting)
        ->and($booking->nationality_code)->toBe('IT');
});

test('an accommodation booking holds the nights so the room cannot be booked twice', function () {
    $room = Room::factory()->create(['base_price' => 80]);
    $payload = [
        'room_id' => $room->id,
        'check_in' => '2026-12-01',
        'check_out' => '2026-12-04',
        'adults' => 2,
        'nationality_code' => 'IT',
        'lead' => ['name' => 'John Smith', 'email' => 'john@example.com'],
        'travelers' => [
            ['first_name' => 'John', 'last_name' => 'Smith', 'type' => 'adult'],
            ['first_name' => 'Kate', 'last_name' => 'Smith', 'type' => 'adult'],
        ],
    ];

    $booking = $this->service->createFromQuote(CreateBookingData::fromArray($payload));

    expect((float) $booking->total)->toBe(240.0)
        ->and($booking->check_in->toDateString())->toBe('2026-12-01')
        ->and($booking->rooms->first()->nights)->toBe(3)
        ->and($booking->rooms->first()->guests)->toHaveCount(2)
        ->and(RoomAvailability::query()->where('booking_id', $booking->id)->pluck('status')->unique()->all())->toBe([RoomAvailabilityStatus::Held]);

    expect(fn () => $this->service->createFromQuote(CreateBookingData::fromArray([...$payload, 'check_in' => '2026-12-03', 'check_out' => '2026-12-05'])))
        ->toThrow(DomainValidationException::class, 'This room is not available on 3 Dec 2026.');
});

test('cancelling a booking logs the change and releases held nights', function () {
    $room = Room::factory()->create();
    $booking = Booking::factory()->create(['bookable_type' => 'room', 'bookable_id' => $room->id]);
    RoomAvailability::factory()->for($room)->create(['date' => '2026-12-01', 'status' => RoomAvailabilityStatus::Held, 'booking_id' => $booking->id]);

    $this->service->changeStatus($booking, BookingStatus::Cancelled, note: 'Customer request');

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->statusLogs()->first()->note)->toBe('Customer request')
        ->and(RoomAvailability::query()->count())->toBe(0);
});
