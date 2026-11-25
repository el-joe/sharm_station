<?php

use App\DTOs\Pricing\QuoteLineData;
use App\Enums\CommissionCategory;
use App\Enums\RoomOptionUnitType;
use App\Enums\TripType;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Destination;
use App\Models\Package;
use App\Models\Partner;
use App\Models\ProductPrice;
use App\Models\RoomOption;
use App\Models\Trip;
use App\Services\Pricing\TripPricingEngine;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    seedCurrencies();
    // Group adult €25 / child €15, bands 0-3 free and 4-11 50 % off; 3 days and 2 nights.
    $this->trip = snorkellingTrip(['type' => TripType::MultiDay, 'days' => 3, 'nights' => 2, 'slug' => 'desert-camp-trip']);
    $this->double = RoomOption::factory()->create(['owner_type' => 'trip', 'owner_id' => $this->trip->id, 'name' => ['en' => 'Double room'], 'stars' => 4, 'price' => 60, 'max_adults' => 2, 'max_children' => 1, 'max_total' => 3]);
    $this->suite = RoomOption::factory()->perPerson()->perStay()->create(['owner_type' => 'trip', 'owner_id' => $this->trip->id, 'unit_type' => RoomOptionUnitType::Suite, 'name' => ['en' => 'Family suite'], 'stars' => 5, 'price' => 100, 'max_adults' => 3, 'max_children' => 2, 'max_total' => 4]);
});

function roomTripQuote(array $overrides = []): array
{
    return ['booking_type' => 'group', 'travel_date' => '2026-11-25', 'adults' => 3, ...$overrides];
}

test('optional rooms are priced per room and per night on top of the trip (SRS 7.1)', function () {
    $this->postJson('/api/v1/trips/desert-camp-trip/quote', roomTripQuote(['room_options' => [
        ['room_option_id' => $this->double->id, 'adults' => 2],
        ['room_option_id' => $this->double->id, 'adults' => 1],
    ]]))
        ->assertOk()
        ->assertJsonPath('data.total', 75 + 240)
        ->assertJsonPath('data.lines.1.type', 'room')
        ->assertJsonPath('data.lines.1.total', 120)
        ->assertJsonPath('data.lines.1.meta.nights', 2)
        ->assertJsonPath('data.lines.1.meta.room_option_id', $this->double->id);
});

test('per-person rooms price children with the trip age bands, per stay', function () {
    $quote = app(TripPricingEngine::class)->quote($this->trip, quoteRequest([
        'travel_date' => '2026-11-25', 'adults' => 2, 'children' => 1, 'child_ages' => [8],
        'room_options' => [['room_option_id' => $this->suite->id, 'adults' => 2, 'children_ages' => [8]]],
    ]));
    $room = $quote->linesOfType(QuoteLineData::TYPE_ROOM)[0];

    expect($quote->isValid())->toBeTrue()
        ->and($room->total)->toBe(250.0)
        ->and($room->meta['guests'][0])->toMatchArray(['type' => 'child', 'age' => 8, 'price' => 50.0, 'discount' => 50.0]);
});

test('the allocation rules apply to trip rooms: everybody in a room, enough places', function () {
    $this->postJson('/api/v1/trips/desert-camp-trip/quote', roomTripQuote(['adults' => 4, 'room_options' => [['room_option_id' => $this->double->id, 'adults' => 2]]]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.room_options.0', 'These rooms cannot fit 4 guests, add a room.');

    $this->postJson('/api/v1/trips/desert-camp-trip/quote', roomTripQuote(['room_options' => [['room_option_id' => $this->suite->id, 'adults' => 2]]]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.room_options.0', 'Every guest must be in a room.');
});

test('room options reject unknown rooms, over-capacity rooms and single-day trips', function () {
    $quote = app(TripPricingEngine::class);

    expect($quote->quote($this->trip, quoteRequest(['travel_date' => '2026-11-25', 'adults' => 3, 'room_options' => [['room_option_id' => 999, 'adults' => 3]]]))->errors['room_options.0.room_option_id'])
        ->toContain('Room option #999 is not available for this trip.')
        ->and($quote->quote($this->trip, quoteRequest(['travel_date' => '2026-11-25', 'adults' => 3, 'room_options' => [['room_option_id' => $this->double->id, 'adults' => 3]]]))->errors['room_options.0.adults'])
        ->toContain('Room 1 sleeps at most 2 adults.');

    $dayTrip = buggyTrip();
    expect($quote->quote($dayTrip, quoteRequest(['travel_date' => '2026-11-25', 'adults' => 1, 'units' => [['unit_option_id' => $dayTrip->unitOptions[0]->id, 'quantity' => 1]], 'room_options' => [['room_option_id' => $this->double->id, 'adults' => 1]]]))->errors['room_options'])
        ->toContain('Rooms can only be added to multi-day products.');
});

test('room prices follow the nationality price matrix (PriceResolver)', function () {
    ProductPrice::factory()->create(['priceable_type' => 'room_option', 'priceable_id' => $this->double->id, 'nationality_group_id' => nationalityGroup('egyptian')->id, 'adult_price' => null, 'child_price' => null, 'price' => 40]);

    $quote = app(TripPricingEngine::class)->quote($this->trip, quoteRequest([
        'travel_date' => '2026-11-25', 'adults' => 2, 'room_options' => [['room_option_id' => $this->double->id, 'adults' => 2]],
    ], nationality: 'EG'));

    expect($quote->linesOfType(QuoteLineData::TYPE_ROOM)[0]->total)->toBe(80.0)
        ->and($quote->linesOfType(QuoteLineData::TYPE_ROOM)[0]->meta['price_source'])->toBe('group_base');
});

test('a partner booking with rooms gets the commission on the rooms and stores them with their guests', function () {
    $partner = Partner::factory()->create();
    partnerOverride($partner, CommissionCategory::MultiDayTrips, 10);
    Sanctum::actingAs($partner->user);

    $response = $this->postJson('/api/v1/partner/bookings', [
        'product_type' => 'trip',
        'slug' => 'desert-camp-trip',
        'nationality_code' => 'IT',
        ...roomTripQuote(['adults' => 2, 'children' => 1, 'child_ages' => [8]]),
        'room_options' => [['room_option_id' => $this->double->id, 'adults' => 2, 'children_ages' => [8]]],
        'lead' => ['name' => 'Client One', 'email' => 'client@example.com'],
        'travelers' => [
            ['first_name' => 'Client', 'last_name' => 'One', 'type' => 'adult'],
            ['first_name' => 'Second', 'last_name' => 'One', 'type' => 'adult'],
            ['first_name' => 'Kid', 'last_name' => 'One', 'type' => 'child', 'age' => 8],
        ],
    ])->assertCreated();

    $booking = Booking::query()->where('reference', $response->json('data.reference'))->sole();
    $room = $booking->rooms()->sole();

    expect((float) $booking->retail_total)->toBe(25 * 2 + 12.5 + 120.0)
        ->and((float) $booking->partner_total)->toBe(round((25 * 2 + 12.5 + 120.0) * 0.9, 2))
        ->and($room->room_option_id)->toBe($this->double->id)
        ->and((float) $room->partner_total)->toBe(108.0)
        ->and($room->child_ages)->toBe([8])
        ->and($room->guests()->count())->toBe(3);
});

test('the trip detail lists its optional rooms with the base price', function () {
    $this->getJson('/api/v1/trips/desert-camp-trip')
        ->assertOk()
        ->assertJsonCount(2, 'data.room_options')
        ->assertJsonPath('data.room_options.0.unit_type', 'room')
        ->assertJsonPath('data.room_options.0.price.amount_eur', 60)
        ->assertJsonPath('data.room_options.0.nights', 2)
        ->assertJsonPath('data.room_options.1.pricing_mode', 'per_person');
});

test('package rooms belong to a stage and use its nights; every guest needs a room in that stage', function () {
    $package = Package::factory()->create(['slug' => 'nile-and-sea', 'days' => 5, 'nights' => 4, 'base_price' => 500, 'base_child_price' => 300]);
    $destination = Destination::factory()->create();
    $package->stages()->create(['destination_id' => $destination->id, 'position' => 1, 'days' => 3, 'nights' => 3]);
    $package->stages()->create(['destination_id' => $destination->id, 'position' => 2, 'days' => 2, 'nights' => 1]);
    $stageRoom = RoomOption::factory()->create(['owner_type' => 'package', 'owner_id' => $package->id, 'stage_position' => 1, 'price' => 50, 'max_adults' => 2, 'max_total' => 2]);

    $this->postJson('/api/v1/packages/nile-and-sea/quote', ['booking_type' => 'group', 'travel_date' => '2026-11-25', 'adults' => 2, 'room_options' => [['room_option_id' => $stageRoom->id, 'adults' => 2]]])
        ->assertOk()
        ->assertJsonPath('data.total', 1150)
        ->assertJsonPath('data.lines.1.meta.stage_position', 1)
        ->assertJsonPath('data.lines.1.meta.nights', 3);

    $this->postJson('/api/v1/packages/nile-and-sea/quote', ['booking_type' => 'group', 'travel_date' => '2026-11-25', 'adults' => 3, 'room_options' => [['room_option_id' => $stageRoom->id, 'adults' => 2]]])
        ->assertUnprocessable()
        ->assertJsonPath('errors.room_options.0', 'Stage 1: these rooms cannot fit 3 guests, add a room.');

    $this->getJson('/api/v1/packages/nile-and-sea')->assertOk()->assertJsonPath('data.room_options.0.stage_position', 1)->assertJsonPath('data.room_options.0.nights', 3);
});

describe('admin', function () {
    beforeEach(fn () => $this->actingAs(staffUser()));

    test('staff add, edit and remove the rooms of a trip and set their price matrix', function () {
        $this->get(route('admin.trips.room-options', $this->trip))->assertOk()->assertSee('Family suite');

        $this->post(route('admin.trips.room-options.store', $this->trip), [
            'name' => ['en' => 'Twin tent'], 'unit_type' => 'tent', 'stars' => 3, 'pricing_mode' => 'per_room', 'price_period' => 'per_stay',
            'price' => 90, 'max_adults' => 2, 'max_children' => 0, 'max_total' => 2, 'max_quantity' => 5, 'is_active' => 1,
        ])->assertRedirect();

        $tent = RoomOption::query()->latest('id')->firstOrFail();
        expect($tent->owner->is($this->trip))->toBeTrue()->and($tent->stage_position)->toBeNull();

        $this->get(route('admin.room-options.edit', $tent))->assertOk()->assertSee('Price matrix');
        $this->put(route('admin.room-options.seasons.update', $tent), [
            'base' => [nationalityGroup('egyptian')->id => ['price' => 70]],
            'seasons' => [],
        ])->assertRedirect();
        expect(ProductPrice::query()->where('priceable_type', 'room_option')->where('priceable_id', $tent->id)->value('price'))->toEqual(70);

        $this->put(route('admin.room-options.update', $tent), [
            'name' => ['en' => 'Twin tent'], 'unit_type' => 'tent', 'pricing_mode' => 'per_room', 'price_period' => 'per_stay',
            'price' => 95, 'max_adults' => 3, 'max_children' => 0, 'max_total' => 2, 'max_quantity' => 5,
        ])->assertSessionHasErrors('max_total');

        $this->delete(route('admin.room-options.destroy', $tent))->assertRedirect(route('admin.trips.room-options', $this->trip));
        expect(RoomOption::query()->find($tent->id))->toBeNull();
    });

    test('package rooms need a stage and single-day trips cannot get rooms', function () {
        $package = Package::factory()->create();
        $package->stages()->create(['destination_id' => Destination::factory()->create()->id, 'position' => 1, 'days' => 2, 'nights' => 2]);
        $fields = ['name' => ['en' => 'Room'], 'unit_type' => 'room', 'pricing_mode' => 'per_room', 'price_period' => 'per_night', 'price' => 50, 'max_adults' => 2, 'max_children' => 0, 'max_total' => 2, 'max_quantity' => 5];

        $this->post(route('admin.packages.room-options.store', $package), $fields)->assertSessionHasErrors('stage_position');
        $this->post(route('admin.packages.room-options.store', $package), [...$fields, 'stage_position' => 1])->assertRedirect();

        $dayTrip = Trip::factory()->create();
        $this->get(route('admin.trips.room-options', $dayTrip))->assertOk()->assertSee('only on multi-day products');
        $this->post(route('admin.trips.room-options.store', $dayTrip), $fields)->assertStatus(422);
    });

    test('editors cannot manage room prices', function () {
        $this->actingAs(staffUser(UserRole::Editor))->get(route('admin.trips.room-options', $this->trip))->assertForbidden();
    });
});
