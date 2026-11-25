<?php

use App\Enums\RoomAvailabilityStatus;
use App\Enums\RoomType;
use App\Models\Booking;
use App\Models\Building;
use App\Models\Partner;
use App\Models\Room;
use App\Models\RoomAvailability;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    seedCurrencies();
    $this->building = Building::factory()->create(['slug' => 'hotel-nile-view']);
    $this->double = Room::factory()->for($this->building)->create(['name' => ['en' => 'Double'], 'room_type' => RoomType::Double, 'max_adults' => 2, 'max_children' => 1, 'max_total' => 2, 'base_price' => 80, 'sort' => 1]);
    $this->triple = Room::factory()->for($this->building)->create(['name' => ['en' => 'Triple'], 'room_type' => RoomType::Triple, 'max_adults' => 3, 'max_children' => 2, 'max_total' => 3, 'base_price' => 100, 'sort' => 2]);
});

function multiRoomBody(array $rooms, array $overrides = []): array
{
    return [
        'check_in' => '2026-12-01',
        'check_out' => '2026-12-03',
        'rooms' => $rooms,
        'nationality_code' => 'IT',
        'lead' => ['name' => 'Anna Rossi', 'email' => 'anna@example.com'],
        'travelers' => [
            ['first_name' => 'Anna', 'last_name' => 'Rossi', 'type' => 'adult'],
            ['first_name' => 'Marco', 'last_name' => 'Rossi', 'type' => 'adult'],
            ['first_name' => 'Luca', 'last_name' => 'Rossi', 'type' => 'adult'],
            ['first_name' => 'Sara', 'last_name' => 'Rossi', 'type' => 'child', 'age' => 9],
        ],
        ...$overrides,
    ];
}

test('a multi-room quote returns one summary per room and the stay total', function () {
    $this->postJson('/api/v1/rooms/quote', [
        'check_in' => '2026-12-01',
        'check_out' => '2026-12-03',
        'rooms' => [['room_id' => $this->double->id, 'adults' => 2], ['room_id' => $this->triple->id, 'adults' => 1, 'children_ages' => [9]]],
    ])
        ->assertOk()
        ->assertJsonPath('data.total', 360)
        ->assertJsonPath('data.building_id', $this->building->id)
        ->assertJsonPath('data.rooms.1.room_id', $this->triple->id)
        ->assertJsonPath('data.rooms.1.children_ages', [9])
        ->assertJsonPath('data.rooms.1.total', 200);
});

test('a multi-room quote breaking the allocation rules answers 422 with keyed errors', function () {
    $response = $this->postJson('/api/v1/rooms/quote?locale=it', [
        'check_in' => '2026-12-01',
        'check_out' => '2026-12-03',
        'rooms' => [['room_id' => $this->double->id, 'adults' => 0, 'children_ages' => [9]]],
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors')['rooms.0.adults'])->toContain('Un bambino non può stare in una camera senza un adulto.');

    $this->postJson('/api/v1/rooms/quote', ['check_in' => '2026-12-01', 'check_out' => '2026-12-03', 'rooms' => [['room_id' => $this->double->id, 'adults' => 1], ['room_id' => $this->double->id, 'adults' => 1]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rooms.1.room_id']);
});

test('a multi-room booking stores every room with its guests and holds the nights of each room', function () {
    $response = $this->postJson('/api/v1/bookings', multiRoomBody([
        ['room_id' => $this->double->id, 'adults' => 2],
        ['room_id' => $this->triple->id, 'adults' => 1, 'children_ages' => [9]],
    ]))
        ->assertCreated()
        ->assertJsonPath('data.service_type', 'accommodation')
        ->assertJsonPath('data.totals.total', 360)
        ->assertJsonPath('data.end_date', '2026-12-03')
        ->assertJsonCount(2, 'data.rooms')
        ->assertJsonPath('data.rooms.1.children_ages', [9]);

    $booking = Booking::query()->where('reference', $response->json('data.reference'))->sole();
    $triple = $booking->rooms()->where('room_id', $this->triple->id)->sole();

    expect($booking->adults)->toBe(3)
        ->and($booking->children)->toBe(1)
        ->and($triple->guests()->with('traveler')->get()->pluck('traveler.first_name')->all())->toEqualCanonicalizing(['Luca', 'Sara'])
        ->and(RoomAvailability::query()->where('booking_id', $booking->id)->where('status', RoomAvailabilityStatus::Held)->count())->toBe(4);
});

test('a multi-room booking is rejected when a room was taken meanwhile', function () {
    RoomAvailability::factory()->create(['room_id' => $this->triple->id, 'date' => '2026-12-02', 'status' => RoomAvailabilityStatus::Booked]);

    $this->postJson('/api/v1/bookings', multiRoomBody([
        ['room_id' => $this->double->id, 'adults' => 2],
        ['room_id' => $this->triple->id, 'adults' => 1, 'children_ages' => [9]],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['check_in']);

    expect(Booking::query()->count())->toBe(0);
});

test('partners quote several rooms at the customer price (no accommodation commission)', function () {
    $partner = Partner::factory()->create();
    Sanctum::actingAs($partner->user);

    $this->postJson('/api/v1/partner/quotes', [
        'product_type' => 'room',
        'nationality_code' => 'IT',
        'check_in' => '2026-12-01',
        'check_out' => '2026-12-03',
        'rooms' => [['room_id' => $this->double->id, 'adults' => 2], ['room_id' => $this->triple->id, 'adults' => 1]],
    ])
        ->assertOk()
        ->assertJsonPath('data.retail_total', 360)
        ->assertJsonPath('data.partner_total', 360)
        ->assertJsonPath('data.commission_amount', 0);
});

describe('best combinations', function () {
    test('combinations are ranked by total price and hold the whole party under the allocation rules', function () {
        $single = Room::factory()->for($this->building)->create(['name' => ['en' => 'Single'], 'room_type' => RoomType::Single, 'max_adults' => 1, 'max_children' => 0, 'max_total' => 1, 'base_price' => 50]);

        $response = $this->getJson('/api/v1/rooms/combinations?building=hotel-nile-view&check_in=2026-12-01&check_out=2026-12-03&adults=3&child_ages[]=9')
            ->assertOk()
            ->assertJsonPath('meta.nights', 2);

        $combinations = $response->json('data');
        $totals = array_column($combinations, 'total_eur');

        expect($totals)->toBe(collect($totals)->sort()->values()->all())
            ->and($combinations[0]['total_eur'])->toEqual(300)
            ->and(collect($combinations[0]['rooms'])->pluck('room_id')->all())->toEqualCanonicalizing([$single->id, $this->triple->id])
            ->and(collect($combinations)->firstWhere('total_eur', 360)['rooms'])->toHaveCount(2)
            ->and(collect($combinations)->every(fn (array $combination): bool => collect($combination['rooms'])->every(fn (array $room): bool => $room['adults'] >= 1)))->toBeTrue()
            ->and($combinations[0]['request']['rooms'])->toHaveCount(2);
    });

    test('extra beds make a combination possible within the room limit, priced per night and sent back in the request', function () {
        $this->double->update(['max_extra_beds' => 1, 'extra_bed_price' => 15, 'max_cots' => 1, 'cot_price' => 5]);
        $this->triple->update(['max_extra_beds' => 1, 'extra_bed_price' => 15]);
        RoomAvailability::factory()->create(['room_id' => $this->triple->id, 'date' => '2026-12-01', 'status' => RoomAvailabilityStatus::Booked]);

        $combinations = $this->getJson('/api/v1/rooms/combinations?building=hotel-nile-view&check_in=2026-12-01&check_out=2026-12-03&adults=2&child_ages[]=7')
            ->assertOk()->json('data');

        expect($combinations)->toHaveCount(1)
            ->and($combinations[0]['total_eur'])->toEqual(190)
            ->and($combinations[0]['extra_beds'])->toBe(1)
            ->and($combinations[0]['rooms'][0])->toMatchArray(['room_id' => $this->double->id, 'adults' => 2, 'children_ages' => [7], 'extra_beds' => 1, 'cots' => 0])
            ->and($combinations[0]['rooms'][0]['capacity'])->toMatchArray(['max_total' => 2, 'max_extra_beds' => 1, 'max_cots' => 1])
            ->and($combinations[0]['request']['rooms'][0])->toMatchArray(['room_id' => $this->double->id, 'extra_beds' => 1, 'cots' => 0]);

        $this->postJson('/api/v1/rooms/quote', [...$combinations[0]['request'], 'adults' => 2, 'child_ages' => [7]])
            ->assertOk()->assertJsonPath('data.total', 190);

        $this->getJson('/api/v1/rooms/combinations?building=hotel-nile-view&check_in=2026-12-01&check_out=2026-12-03&adults=3')
            ->assertOk()->assertJsonCount(0, 'data');
    });

    test('a cheaper extra bed beats a second room, and no extra bed is added when the party fits', function () {
        $this->triple->update(['max_extra_beds' => 1, 'extra_bed_price' => 10]);
        Room::factory()->for($this->building)->create(['name' => ['en' => 'Single'], 'room_type' => RoomType::Single, 'max_adults' => 1, 'max_children' => 0, 'max_total' => 1, 'base_price' => 50]);

        $four = $this->getJson('/api/v1/rooms/combinations?building=hotel-nile-view&check_in=2026-12-01&check_out=2026-12-03&adults=3&child_ages[]=9')->assertOk()->json('data.0');
        expect($four['total_eur'])->toEqual(220)
            ->and($four['rooms'])->toHaveCount(1)
            ->and($four['rooms'][0])->toMatchArray(['room_id' => $this->triple->id, 'extra_beds' => 1]);

        $three = $this->getJson('/api/v1/rooms/combinations?building=hotel-nile-view&check_in=2026-12-01&check_out=2026-12-03&adults=3')->assertOk()->json('data.0');
        expect($three['total_eur'])->toEqual(200)
            ->and($three['extra_beds'])->toBe(0);
    });

    test('booked rooms are left out and an impossible party returns an empty list with the reason', function () {
        RoomAvailability::factory()->create(['room_id' => $this->triple->id, 'date' => '2026-12-01', 'status' => RoomAvailabilityStatus::Booked]);

        $this->getJson('/api/v1/rooms/combinations?building=hotel-nile-view&check_in=2026-12-01&check_out=2026-12-03&adults=3')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.message', 'These rooms cannot fit 3 guests, add a room.');

        $this->getJson('/api/v1/rooms/combinations?building=unknown&check_in=2026-12-01&check_out=2026-12-03&adults=2')->assertNotFound();
        $this->getJson('/api/v1/rooms/combinations?building=hotel-nile-view&adults=2')->assertUnprocessable()->assertJsonValidationErrors(['check_in', 'check_out']);
    });
});

describe('rooms listing availability', function () {
    test('unavailable rooms are listed after the bookable ones with their reason', function () {
        $closed = Room::factory()->for($this->building)->create(['min_nights' => 1]);
        $longStay = Room::factory()->for($this->building)->create(['min_nights' => 5]);
        RoomAvailability::factory()->create(['room_id' => $this->double->id, 'date' => '2026-12-02', 'status' => RoomAvailabilityStatus::Booked]);
        RoomAvailability::factory()->create(['room_id' => $closed->id, 'date' => '2026-12-01', 'status' => RoomAvailabilityStatus::Closed]);

        $rooms = collect($this->getJson('/api/v1/rooms?check_in=2026-12-01&check_out=2026-12-03&guests=3')->assertOk()->json('data'))->keyBy('id');

        expect($rooms[$this->triple->id])->toMatchArray(['available' => true, 'unavailable_reason' => null])
            ->and($rooms[$this->double->id]['unavailable_reason'])->toBe('booked')
            ->and($rooms[$this->double->id]['unavailable_reasons'])->toBe(['booked', 'capacity'])
            ->and($rooms[$closed->id]['unavailable_reason'])->toBe('closed')
            ->and($rooms[$longStay->id]['unavailable_reason'])->toBe('min_stay')
            ->and($rooms->keys()->first())->toBe($this->triple->id);
    });

    test('group_by=building groups the page by building', function () {
        $other = Room::factory()->for(Building::factory()->create(['slug' => 'red-sea-resort']))->create();

        $this->getJson('/api/v1/rooms?group_by=building&check_in=2026-12-01&check_out=2026-12-03')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.building.slug', 'hotel-nile-view')
            ->assertJsonCount(2, 'data.0.rooms')
            ->assertJsonPath('data.0.available_count', 2)
            ->assertJsonPath('data.1.rooms.0.id', $other->id)
            ->assertJsonStructure(['meta' => ['total']]);
    });
});
