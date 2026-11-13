<?php

use App\Enums\TripType;
use App\Models\Partner;
use App\Models\SeasonPrice;
use App\Models\Trip;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    seedCurrencies();
});

describe('listing', function () {
    test('only published trips are listed with card data', function () {
        Trip::factory()->create(['title' => ['en' => 'Snorkelling', 'it' => 'Snorkeling'], 'from_price' => 25]);
        Trip::factory()->draft()->create();

        $this->getJson('/api/v1/trips')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Snorkelling')
            ->assertJsonPath('data.0.pricing_mode', 'per_person')
            ->assertJsonPath('data.0.from_price.amount_eur', 25)
            ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'total']]);
    });

    test('trips can be filtered by type, pricing mode, booking type and price', function () {
        Trip::factory()->create(['from_price' => 25]);
        Trip::factory()->nile()->create(['from_price' => 480]);
        Trip::factory()->perUnit()->privateOnly()->create(['from_price' => 35]);

        $this->getJson('/api/v1/trips?type=nile')->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', TripType::Nile->value);
        $this->getJson('/api/v1/trips?type=single_day,nile')->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/trips?pricing_mode=per_unit')->assertJsonCount(1, 'data')->assertJsonPath('data.0.from_price.amount_eur', 35);
        $this->getJson('/api/v1/trips?booking_type=private')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/trips?price_min=30&price_max=100')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/trips?sort=price_desc')->assertJsonPath('data.0.from_price.amount_eur', 480);
    });

    test('price filters are read in the requested currency', function () {
        Trip::factory()->create(['from_price' => 100]);

        $this->getJson('/api/v1/trips?currency=EGP&price_max=4000')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/trips?currency=EGP&price_max=6000')->assertJsonCount(1, 'data')->assertJsonPath('data.0.from_price.currency', 'EGP');
    });

    test('there is no date filter on the trip listing', function () {
        Trip::factory()->count(2)->create();

        $this->getJson('/api/v1/trips?date=2026-12-01&travel_date=2026-12-01')->assertOk()->assertJsonCount(2, 'data');
    });

    test('invalid filters are rejected', function () {
        $this->getJson('/api/v1/trips?type=cruise&pricing_mode=per_day')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type.0', 'pricing_mode']);

        $this->getJson('/api/v1/trips?price_min=50&price_max=10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price_max']);
    });
});

describe('detail', function () {
    test('a per unit trip exposes its pricing mode, unit options and price list', function () {
        $trip = buggyTrip();
        SeasonPrice::factory()->between('2026-11-01', '2026-12-19', 'High season')->recurring()->create([
            'priceable_id' => $trip->id,
            'adult_price' => null,
            'unit_prices' => [$trip->unitOptions[0]->id => 40],
        ]);

        $this->getJson('/api/v1/trips/desert-buggy-safari?locale=it&currency=USD')
            ->assertOk()
            ->assertJsonPath('data.title', 'Safari in buggy')
            ->assertJsonPath('data.pricing_mode', 'per_unit')
            ->assertJsonPath('data.min_persons', 1)
            ->assertJsonPath('data.max_persons', null)
            ->assertJsonCount(3, 'data.unit_options')
            ->assertJsonPath('data.unit_options.0.name', 'Buggy singolo')
            ->assertJsonPath('data.unit_options.0.seats', 1)
            ->assertJsonPath('data.unit_options.0.price', ['amount_eur' => 35, 'amount' => 37.8, 'currency' => 'USD'])
            ->assertJsonPath('data.price_list.0.name', 'High season')
            ->assertJsonPath('data.price_list.0.is_current', true)
            ->assertJsonPath('data.price_list.0.starts_on', '2026-11-01')
            ->assertJsonPath('data.price_list.0.prices.units.0.price.amount_eur', 40)
            ->assertJsonPath('data.availability.group.enabled', true)
            ->assertJsonPath('data.group_prices', null);
    });

    test('a per person trip exposes max persons, group prices, private tiers and child bands', function () {
        snorkellingTrip();

        $this->getJson('/api/v1/trips/ras-mohammed-snorkelling')
            ->assertOk()
            ->assertJsonPath('data.pricing_mode', 'per_person')
            ->assertJsonPath('data.max_persons', 12)
            ->assertJsonPath('data.group_prices.adult.amount_eur', 25)
            ->assertJsonCount(3, 'data.private_tiers')
            ->assertJsonPath('data.private_tiers.1.label', '3-4')
            ->assertJsonPath('data.child_policy.bands.1.label', '4-11 years (50% off)')
            ->assertJsonPath('data.unit_options', []);
    });

    test('partner prices are never exposed to visitors or customers', function () {
        $trip = buggyTrip();
        $trip->unitOptions[0]->update(['partner_price' => 30]);

        $guest = $this->getJson('/api/v1/trips/desert-buggy-safari')->assertOk();
        expect($guest->json('data'))->not->toHaveKey('partner_from_price')
            ->and($guest->json('data.unit_options.0'))->not->toHaveKey('partner_price')
            ->and($guest->getContent())->not->toContain('partner_price');

        Sanctum::actingAs(User::factory()->create());
        $customer = $this->getJson('/api/v1/trips/desert-buggy-safari')->assertOk();
        expect($customer->getContent())->not->toContain('partner_price');

        $pending = Partner::factory()->pending()->create();
        Sanctum::actingAs($pending->user);
        expect($this->getJson('/api/v1/trips/desert-buggy-safari')->getContent())->not->toContain('partner_price');
    });

    test('approved partners see partner prices', function () {
        $trip = buggyTrip();
        $trip->unitOptions[0]->update(['partner_price' => 30]);
        $partner = Partner::factory()->create(['default_commission' => 10]);
        Sanctum::actingAs($partner->user);

        $this->getJson('/api/v1/trips/desert-buggy-safari')
            ->assertOk()
            ->assertJsonPath('data.unit_options.0.partner_price.amount_eur', 30)
            ->assertJsonPath('data.unit_options.1.partner_price.amount_eur', 45);
    });

    test('draft and unknown trips return 404', function () {
        Trip::factory()->draft()->create(['slug' => 'secret-trip']);

        $this->getJson('/api/v1/trips/secret-trip')->assertNotFound();
        $this->getJson('/api/v1/trips/unknown')->assertNotFound();
    });
});
