<?php

use App\Enums\RoomAvailabilityStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Building;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Services\RoomService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-11-24 10:00'));
    $this->admin = staffUser();
    $this->building = Building::factory()->create(['name' => ['en' => 'Coral Bay Residence']]);
    $this->room = Room::factory()->for($this->building)->create(['name' => ['en' => 'Sea view double']]);
});

test('the grid shows rooms by building with booked, held and blocked nights', function () {
    $booking = Booking::factory()->create(['reference' => 'BK-7001']);
    RoomAvailability::factory()->create(['room_id' => $this->room->id, 'date' => '2026-12-05', 'status' => RoomAvailabilityStatus::Booked, 'booking_id' => $booking->id]);
    RoomAvailability::factory()->create(['room_id' => $this->room->id, 'date' => '2026-12-06', 'status' => RoomAvailabilityStatus::Held, 'booking_id' => $booking->id, 'held_until' => now()->addHour()]);
    RoomAvailability::factory()->create(['room_id' => $this->room->id, 'date' => '2026-12-07', 'status' => RoomAvailabilityStatus::Closed]);
    $other = Room::factory()->for(Building::factory()->create(['name' => ['en' => 'Desert Lodge']]))->create(['name' => ['en' => 'Tent']]);

    $this->actingAs($this->admin)->get(route('admin.calendar.index', ['month' => '2026-12']))
        ->assertOk()
        ->assertSee('December 2026')
        ->assertSee('Coral Bay Residence')->assertSee('Desert Lodge')
        ->assertSee(route('admin.bookings.show', $booking), false)
        ->assertSee('Booked (1)')->assertSee('Held (1)')->assertSee('Blocked (1)')
        ->assertSee('data-date="2026-12-07" data-status="blocked"', false);

    $this->actingAs($this->admin)->get(route('admin.calendar.index', ['month' => '2026-12', 'building_id' => $other->building_id]))
        ->assertOk()->assertSee('Tent')->assertDontSee('Sea view double');

    $grid = app(RoomService::class)->occupancy(CarbonImmutable::parse('2026-12-01'));
    expect($grid['days'])->toHaveCount(31)
        ->and($grid['buildings'][0]['rooms'][0]['cells'][4])->toMatchArray(['status' => 'booked', 'booking' => ['id' => $booking->id, 'reference' => 'BK-7001', 'lead_name' => $booking->lead_name]]);
});

test('a free range is blocked and a blocked night is freed; booked nights stay untouched', function () {
    $booking = Booking::factory()->create();
    RoomAvailability::factory()->create(['room_id' => $this->room->id, 'date' => '2026-12-11', 'status' => RoomAvailabilityStatus::Booked, 'booking_id' => $booking->id]);

    $this->actingAs($this->admin)->postJson(route('admin.calendar.block'), ['room_id' => $this->room->id, 'from' => '2026-12-10', 'to' => '2026-12-12', 'status' => 'closed'])
        ->assertOk()
        ->assertJson(['status' => 'blocked', 'changed' => 2, 'dates' => ['2026-12-10', '2026-12-11', '2026-12-12']]);

    expect(RoomAvailability::query()->where('room_id', $this->room->id)->orderBy('date')->pluck('status')->map->value->all())
        ->toBe(['closed', 'booked', 'closed']);

    $this->actingAs($this->admin)->postJson(route('admin.calendar.block'), ['room_id' => $this->room->id, 'from' => '2026-12-10', 'to' => '2026-12-10', 'status' => 'open'])
        ->assertOk()->assertJson(['status' => 'free', 'changed' => 1]);
    expect(RoomAvailability::query()->where('room_id', $this->room->id)->count())->toBe(2);

    // Both changes are in the activity log (A7).
    expect(ActivityLog::query()->where('subject_type', 'room')->where('subject_id', $this->room->id)->orderBy('id')->pluck('description')->all())
        ->toBe(['Room “Sea view double”: 2 nights blocked (2026-12-10 – 2026-12-12)', 'Room “Sea view double”: 1 night unblocked (2026-12-10)']);
});

test('past nights and invalid ranges are rejected', function () {
    $this->actingAs($this->admin)->postJson(route('admin.calendar.block'), ['room_id' => $this->room->id, 'from' => '2026-11-01', 'to' => '2026-11-02', 'status' => 'closed'])
        ->assertUnprocessable()->assertJsonValidationErrors(['from' => 'Past nights cannot be changed.']);
    $this->actingAs($this->admin)->postJson(route('admin.calendar.block'), ['room_id' => $this->room->id, 'from' => '2026-12-05', 'to' => '2026-12-01', 'status' => 'closed'])
        ->assertUnprocessable()->assertJsonValidationErrors('to');
    $this->actingAs($this->admin)->postJson(route('admin.calendar.block'), ['room_id' => $this->room->id, 'from' => '2026-12-05', 'to' => '2026-12-06', 'status' => 'booked'])
        ->assertUnprocessable()->assertJsonValidationErrors('status');
});

test('editors can see the calendar without booking links', function () {
    $booking = Booking::factory()->create();
    RoomAvailability::factory()->create(['room_id' => $this->room->id, 'date' => '2026-12-05', 'status' => RoomAvailabilityStatus::Booked, 'booking_id' => $booking->id]);

    $this->actingAs(staffUser(UserRole::Editor))->get(route('admin.calendar.index', ['month' => '2026-12']))
        ->assertOk()->assertDontSee(route('admin.bookings.show', $booking), false);
});
