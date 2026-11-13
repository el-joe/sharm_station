<?php

use App\Enums\BookingType;
use App\Models\Trip;
use App\Models\TripAvailabilityRule;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    $this->service = app(AvailabilityService::class);
});

test('private trips can be booked on any open day after the lead time', function () {
    $trip = Trip::factory()->privateOnly()->create();
    TripAvailabilityRule::factory()->for($trip)->private()->create(['lead_time_hours' => 48, 'blackout_dates' => ['2026-11-20']]);
    $trip->load('availabilityRules');

    expect($this->service->isAvailable($trip, BookingType::Private, CarbonImmutable::parse('2026-11-14')))->toBeFalse()
        ->and($this->service->check($trip, BookingType::Private, CarbonImmutable::parse('2026-11-14')))->toContain('48 hours')
        ->and($this->service->isAvailable($trip, BookingType::Private, CarbonImmutable::parse('2026-11-15')))->toBeTrue()
        ->and($this->service->isAvailable($trip, BookingType::Private, CarbonImmutable::parse('2026-11-19')))->toBeTrue()
        ->and($this->service->check($trip, BookingType::Private, CarbonImmutable::parse('2026-11-20')))->toBe('20 Nov 2026 is not available for Private bookings.');
});

test('group trips can only be booked on their departure weekdays or listed dates', function () {
    $trip = Trip::factory()->create(['group_enabled' => true]);
    TripAvailabilityRule::factory()->for($trip)->create(['weekdays' => [1, 4], 'dates' => ['2026-11-21'], 'lead_time_hours' => 24]);
    $trip->load('availabilityRules');

    expect($this->service->isAvailable($trip, BookingType::Group, CarbonImmutable::parse('2026-11-16')))->toBeTrue()
        ->and($this->service->isAvailable($trip, BookingType::Group, CarbonImmutable::parse('2026-11-19')))->toBeTrue()
        ->and($this->service->isAvailable($trip, BookingType::Group, CarbonImmutable::parse('2026-11-21')))->toBeTrue()
        ->and($this->service->check($trip, BookingType::Group, CarbonImmutable::parse('2026-11-17')))->toBe('Group departures run on Mondays and Thursdays. Choose another date.');
});

test('dates in the past and disabled booking types are rejected', function () {
    $trip = Trip::factory()->create(['group_enabled' => true, 'private_enabled' => false]);
    TripAvailabilityRule::factory()->for($trip)->create();
    $trip->load('availabilityRules');

    expect($this->service->check($trip, BookingType::Group, CarbonImmutable::parse('2026-11-01')))->toBe('The travel date is in the past.')
        ->and($this->service->check($trip, BookingType::Private, CarbonImmutable::parse('2026-11-20')))->toBe('This trip cannot be booked as Private.');
});

test('a group trip without a departure rule has no bookable dates', function () {
    $trip = Trip::factory()->create(['group_enabled' => true])->load('availabilityRules');

    expect($this->service->check($trip, BookingType::Group, CarbonImmutable::parse('2026-11-20')))->toBe('No group departures are scheduled for this trip.');
});

test('available dates lists the bookable days of the coming period', function () {
    $trip = Trip::factory()->create(['group_enabled' => true]);
    TripAvailabilityRule::factory()->for($trip)->create(['weekdays' => [1], 'lead_time_hours' => 24]);
    $trip->load('availabilityRules');

    expect($this->service->availableDates($trip, BookingType::Group, days: 14))->toBe(['2026-11-16', '2026-11-23']);
});
