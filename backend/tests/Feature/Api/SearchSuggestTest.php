<?php

use App\DTOs\Search\SuggestQueryData;
use App\Enums\TripType;
use App\Models\Building;
use App\Models\Destination;
use App\Models\TransferFixedRoute;
use App\Models\TransferHub;
use App\Models\Trip;

beforeEach(function () {
    $this->hurghada = Destination::factory()->create(['slug' => 'hurghada', 'name' => ['en' => 'Hurghada', 'it' => 'Hurghada']]);
    $this->luxor = Destination::factory()->create(['slug' => 'luxor', 'name' => ['en' => 'Luxor', 'it' => 'Luxor']]);
});

test('every word must match the name or the location', function () {
    Building::factory()->for($this->hurghada)->create(['slug' => 'star-apartment', 'name' => ['en' => 'Star Apartment', 'it' => 'Star Apartment'], 'address' => 'Sheraton Road']);
    Building::factory()->for($this->luxor)->create(['slug' => 'star-luxor', 'name' => ['en' => 'Star Luxor', 'it' => 'Star Luxor'], 'address' => 'Corniche']);
    Building::factory()->for($this->hurghada)->create(['slug' => 'coral', 'name' => ['en' => 'Coral Resort', 'it' => 'Coral Resort'], 'address' => 'Village Road']);

    $response = $this->getJson('/api/v1/search/suggest?scope=stays&q=hurghada%20sta')->assertOk();

    $hotels = collect($response->json('data'))->firstWhere('key', 'hotels');
    expect(collect($hotels['items'])->pluck('value')->all())->toBe(['star-apartment'])
        ->and($hotels['items'][0]['location'])->toBe('Sheraton Road, Hurghada, Egypt')
        ->and($hotels['items'][0]['type'])->toBe('hotel');
});

test('matching ignores case and the location line is localized', function () {
    Building::factory()->for($this->luxor)->create(['slug' => 'nile-view', 'name' => ['en' => 'Hotel Nile View', 'it' => 'Hotel Nile View'], 'address' => 'East Bank']);

    $response = $this->getJson('/api/v1/search/suggest?scope=stays&q=NILE&locale=it')->assertOk();

    $hotels = collect($response->json('data'))->firstWhere('key', 'hotels');
    expect($hotels['items'][0]['location'])->toBe('East Bank, Luxor, Egitto');
});

test('an empty query lists destinations only, without products', function () {
    Trip::factory()->for($this->hurghada)->create();

    $response = $this->getJson('/api/v1/search/suggest?scope=trips')->assertOk();

    expect(collect($response->json('data'))->pluck('key')->all())->toBe(['destinations'])
        ->and(collect($response->json('data.0.items'))->firstWhere('value', 'hurghada')['meta']['trips_count'])->toBe(1);
});

test('the trips scope suggests matching trips but never nile trips or drafts', function () {
    Trip::factory()->for($this->hurghada)->create(['slug' => 'giftun-snorkel', 'title' => ['en' => 'Giftun island snorkelling', 'it' => 'Snorkeling a Giftun'], 'type' => TripType::SingleDay]);
    Trip::factory()->for($this->hurghada)->create(['slug' => 'giftun-nile', 'title' => ['en' => 'Giftun Nile cruise', 'it' => 'Crociera Giftun'], 'type' => TripType::Nile]);
    Trip::factory()->for($this->hurghada)->draft()->create(['slug' => 'giftun-draft', 'title' => ['en' => 'Giftun draft', 'it' => 'Giftun bozza']]);

    $response = $this->getJson('/api/v1/search/suggest?scope=trips&q=giftun')->assertOk();

    $trips = collect($response->json('data'))->firstWhere('key', 'trips');
    expect(collect($trips['items'])->pluck('value')->all())->toBe(['giftun-snorkel']);
});

test('transfer drop-off suggests the hub places and hotels of its destination with coordinates', function () {
    $hub = TransferHub::factory()->create(['destination_id' => $this->hurghada->id]);
    TransferFixedRoute::factory()->create(['transfer_hub_id' => $hub->id, 'destination_name' => ['en' => 'El Gouna', 'it' => 'El Gouna'], 'lat' => 27.39, 'lng' => 33.68]);
    Building::factory()->for($this->hurghada)->create(['slug' => 'gouna-lodge', 'name' => ['en' => 'Gouna Lodge', 'it' => 'Gouna Lodge'], 'lat' => 27.4, 'lng' => 33.67]);
    Building::factory()->for($this->luxor)->create(['slug' => 'gouna-elsewhere', 'name' => ['en' => 'Gouna Luxor', 'it' => 'Gouna Luxor']]);

    $response = $this->getJson("/api/v1/search/suggest?scope=transfer_to&hub_id={$hub->id}&q=gouna")->assertOk();

    $groups = collect($response->json('data'))->keyBy('key');
    expect($groups['places']['items'][0]['label'])->toBe('El Gouna')
        ->and($groups['places']['items'][0]['meta']['lat'])->toBe(27.39)
        ->and(collect($groups['hotels']['items'])->pluck('value')->all())->toBe(['gouna-lodge'])
        ->and($groups['hotels']['items'][0]['meta']['lng'])->toBe(33.67);
});

test('location pieces already in the address are not repeated', function () {
    Building::factory()->for($this->luxor)->create(['slug' => 'nile-view', 'name' => ['en' => 'Hotel Nile View', 'it' => 'Hotel Nile View'], 'address' => 'Corniche El Nile, Luxor, Egypt']);

    $response = $this->getJson('/api/v1/search/suggest?scope=stays&q=nile%20view')->assertOk();

    $hotels = collect($response->json('data'))->firstWhere('key', 'hotels');
    expect($hotels['items'][0]['location'])->toBe('Corniche El Nile, Luxor, Egypt');
});

test('transfer pick-up suggests enabled hubs', function () {
    TransferHub::factory()->create(['name' => ['en' => 'Hurghada Airport', 'it' => 'Aeroporto di Hurghada'], 'destination_id' => $this->hurghada->id]);
    TransferHub::factory()->create(['name' => ['en' => 'Hurghada Marina', 'it' => 'Marina'], 'is_enabled' => false]);

    $response = $this->getJson('/api/v1/search/suggest?scope=transfer_from&q=hurgh')->assertOk();

    expect(collect($response->json('data.0.items'))->pluck('label')->all())->toBe(['Hurghada Airport']);
});

test('the scope is required and validated', function () {
    $this->getJson('/api/v1/search/suggest?q=luxor')->assertUnprocessable()->assertJsonValidationErrors('scope');
    $this->getJson('/api/v1/search/suggest?scope=planets')->assertUnprocessable()->assertJsonValidationErrors('scope');
});

test('the query is split into at most five lower-case words', function () {
    expect(SuggestQueryData::tokenize('  Hurghada,  STA-r  a b c d e '))->toBe(['hurghada', 'sta', 'r', 'a', 'b']);
});
