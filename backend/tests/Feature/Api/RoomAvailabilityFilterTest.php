<?php

use App\Enums\RoomAvailabilityStatus;
use App\Models\Building;
use App\Models\Room;
use App\Models\RoomAvailability;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    seedCurrencies();
    $this->building = Building::factory()->create(['free_cancellation_hours' => null, 'amenities' => ['wifi']]);
    $this->free = Room::factory()->for($this->building)->create(['min_nights' => 1, 'max_nights' => null]);
    $this->booked = Room::factory()->for($this->building)->create(['min_nights' => 1]);
    $this->longStay = Room::factory()->for($this->building)->create(['min_nights' => 5]);

    RoomAvailability::factory()->create(['room_id' => $this->booked->id, 'date' => '2026-12-02', 'status' => RoomAvailabilityStatus::Booked]);
});

test('rooms are filtered by availability for the whole stay and by minimum nights', function () {
    $ids = $this->getJson('/api/v1/rooms?check_in=2026-12-01&check_out=2026-12-04&only_available=1')->assertOk()->json('data.*.id');

    expect($ids)->toBe([$this->free->id]);

    // The booked night is the check-out day: the stay is still possible.
    $ids = $this->getJson('/api/v1/rooms?check_in=2026-11-30&check_out=2026-12-02&only_available=1')->assertOk()->json('data.*.id');
    expect($ids)->toEqualCanonicalizing([$this->free->id, $this->booked->id]);
});

test('expired holds do not block the stay', function () {
    RoomAvailability::factory()->create(['room_id' => $this->free->id, 'date' => '2026-12-01', 'status' => RoomAvailabilityStatus::Held, 'held_until' => now()->subMinute()]);

    expect($this->getJson('/api/v1/rooms?check_in=2026-12-01&check_out=2026-12-02')->json('data.*.id'))->toContain($this->free->id);
});

test('the date filter validates the range', function () {
    $this->getJson('/api/v1/rooms?check_in=2026-12-05&check_out=2026-12-01')->assertUnprocessable()->assertJsonValidationErrors(['check_out']);
    $this->getJson('/api/v1/rooms?check_in=2026-12-05')->assertUnprocessable()->assertJsonValidationErrors(['check_out']);
});

test('free cancellation filter and card flags', function () {
    $other = Building::factory()->create(['free_cancellation_hours' => 48, 'amenities' => ['breakfast', 'pool']]);
    $room = Room::factory()->for($other)->create(['amenities' => ['wifi', 'air_conditioning']]);

    foreach (range(0, 24) as $offset) {
        RoomAvailability::factory()->create(['room_id' => $room->id, 'date' => now()->addDays($offset)->toDateString(), 'status' => RoomAvailabilityStatus::Booked]);
    }

    $this->getJson('/api/v1/rooms?free_cancellation=1&locale=it')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.free_cancellation', true)
        ->assertJsonPath('data.0.free_cancellation_hours', 48)
        ->assertJsonPath('data.0.breakfast_included', true)
        ->assertJsonPath('data.0.few_left', true)
        ->assertJsonPath('data.0.amenity_labels.1.label', 'Aria condizionata')
        ->assertJsonPath('data.0.badges', ['tax_included', 'free_cancellation', 'few_left', 'breakfast_included']);

    $this->getJson("/api/v1/rooms/{$this->free->id}")
        ->assertOk()
        ->assertJsonPath('data.free_cancellation', false)
        ->assertJsonPath('data.few_left', false)
        ->assertJsonPath('data.breakfast_included', false);
});
