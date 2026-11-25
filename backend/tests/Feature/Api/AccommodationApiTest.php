<?php

use App\Models\Building;
use App\Models\Destination;
use App\Models\GoogleReview;
use App\Models\Partner;
use App\Models\Room;
use App\Models\SeasonPrice;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    seedCurrencies();
    $this->luxor = Destination::factory()->create(['slug' => 'luxor']);
    $this->building = Building::factory()->for($this->luxor)->create([
        'name' => ['en' => 'Hotel Nile View', 'it' => 'Hotel Nile View'],
        'slug' => 'hotel-nile-view',
        'stars' => 4,
        'google_place_id' => 'ChIJ-nile-view',
    ]);
    $this->room = Room::factory()->for($this->building)->create(['base_price' => 85, 'from_price' => 85]);
});

test('rooms are listed directly with their building data and the tax included flag', function () {
    Room::factory()->for(Building::factory()->taxesExcluded(6.5))->create(['from_price' => 120]);
    Setting::factory()->create(['key' => 'google_reviews.place.ChIJ-nile-view', 'value' => ['rating' => 4.6, 'total' => 842]]);

    $this->getJson('/api/v1/rooms?destination=luxor')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.tax_included', true)
        ->assertJsonPath('data.0.price_unit', 'per_night')
        ->assertJsonPath('data.0.building.name', 'Hotel Nile View')
        ->assertJsonPath('data.0.building.stars', 4)
        ->assertJsonPath('data.0.building.google.rating', 4.6);

    $this->getJson('/api/v1/rooms?sort=price_desc')
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.tax_included', false)
        ->assertJsonPath('data.0.building.tax_per_night.amount_eur', 6.5);
});

test('rooms can be filtered by guests and price', function () {
    Room::factory()->for($this->building)->create(['max_total' => 5, 'max_adults' => 4, 'from_price' => 140]);

    $this->getJson('/api/v1/rooms?guests=4&only_available=1')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/rooms?guests=4')
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.available', true)
        ->assertJsonPath('data.1.available', false)
        ->assertJsonPath('data.1.unavailable_reason', 'capacity');
    $this->getJson('/api/v1/rooms?price_max=100')->assertJsonCount(1, 'data');
});

test('a room shows its price list, building details and other rooms but no partner price for visitors', function () {
    Room::factory()->for($this->building)->create();
    SeasonPrice::factory()->between('2026-12-20', '2027-01-06', 'Christmas & New Year')->create([
        'priceable_type' => 'room',
        'priceable_id' => $this->room->id,
        'adult_price' => null,
        'price_per_night' => 119,
    ]);

    $response = $this->getJson("/api/v1/rooms/{$this->room->id}")
        ->assertOk()
        ->assertJsonPath('data.tax_included', true)
        ->assertJsonPath('data.price_list.0.name', 'Christmas & New Year')
        ->assertJsonPath('data.price_list.0.is_current', false)
        ->assertJsonPath('data.price_list.0.prices.price_per_night.amount_eur', 119)
        ->assertJsonPath('data.building_details.check_in_from', '14:00')
        ->assertJsonCount(1, 'data.other_rooms');

    expect($response->getContent())->not->toContain('partner_from_price');
});

test('partners see the customer room price on the public site', function () {
    Sanctum::actingAs(Partner::factory()->create()->user);

    $this->getJson("/api/v1/rooms/{$this->room->id}")
        ->assertJsonPath('data.from_price.amount_eur', 85)
        ->assertJsonMissingPath('data.partner_from_price');
});

test('an unknown room returns 404', function () {
    $this->getJson('/api/v1/rooms/999999')->assertNotFound();
});

test('a stay quote is priced night by night', function () {
    $this->postJson('/api/v1/rooms/quote', ['room_id' => $this->room->id, 'check_in' => '2026-12-01', 'check_out' => '2026-12-03', 'adults' => 2])
        ->assertOk()
        ->assertJsonPath('data.nights', 2)
        ->assertJsonPath('data.tax_included', true)
        ->assertJsonCount(2, 'data.lines')
        ->assertJsonPath('data.total', 170)
        ->assertJsonPath('data.deposit_amount', 51);
});

test('an invalid stay quote returns 422', function () {
    $this->postJson('/api/v1/rooms/quote', ['room_id' => $this->room->id, 'check_in' => '2026-12-01', 'check_out' => '2026-12-03', 'adults' => 3])
        ->assertUnprocessable()
        ->assertJsonPath('errors.adults.0', 'This room sleeps at most 2 adults.');

    $this->postJson('/api/v1/rooms/quote', ['room_id' => $this->room->id, 'check_in' => '2026-12-03', 'check_out' => '2026-12-01', 'adults' => 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['check_out']);
});

test('a building page shows its rooms, tax flag and Google reviews', function () {
    Setting::factory()->create(['key' => 'google_reviews.place.ChIJ-nile-view', 'value' => ['rating' => 4.6, 'total' => 842]]);
    GoogleReview::factory()->create(['place_id' => 'ChIJ-nile-view']);
    GoogleReview::factory()->hidden()->create(['place_id' => 'ChIJ-nile-view']);

    $this->getJson('/api/v1/buildings/hotel-nile-view')
        ->assertOk()
        ->assertJsonPath('data.name', 'Hotel Nile View')
        ->assertJsonPath('data.tax_included', true)
        ->assertJsonPath('data.google_reviews.rating', 4.6)
        ->assertJsonCount(1, 'data.google_reviews.reviews')
        ->assertJsonCount(1, 'data.rooms')
        ->assertJsonPath('data.rooms.0.tax_included', true);

    $this->getJson('/api/v1/buildings/unknown')->assertNotFound();
});
