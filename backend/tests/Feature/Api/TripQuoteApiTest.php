<?php

use App\Models\Partner;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    seedCurrencies();
});

function buggyQuoteBody(Trip $trip, array $overrides = []): array
{
    return [
        'booking_type' => 'group',
        'travel_date' => '2026-11-25',
        'adults' => 3,
        'children' => 0,
        'units' => [
            ['unit_option_id' => $trip->unitOptions[0]->id, 'quantity' => 1],
            ['unit_option_id' => $trip->unitOptions[1]->id, 'quantity' => 1],
        ],
        ...$overrides,
    ];
}

test('a valid quote returns itemized lines, totals, deposit and the converted amount', function () {
    $trip = buggyTrip();

    $this->postJson('/api/v1/trips/desert-buggy-safari/quote?currency=USD', buggyQuoteBody($trip))
        ->assertOk()
        ->assertJsonPath('data.valid', true)
        ->assertJsonPath('data.pricing_mode', 'per_unit')
        ->assertJsonPath('data.season_name', null)
        ->assertJsonCount(2, 'data.lines')
        ->assertJsonPath('data.lines.0.label', 'Single buggy')
        ->assertJsonPath('data.retail_total', 85)
        ->assertJsonPath('data.total', 85)
        ->assertJsonPath('data.deposit_amount', 25.5)
        ->assertJsonPath('data.balance_amount', 59.5)
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonPath('data.converted.total', 91.8)
        ->assertJsonMissingPath('data.partner_total');
});

test('an invalid party returns 422 with the business errors', function () {
    $trip = buggyTrip();

    $this->postJson('/api/v1/trips/desert-buggy-safari/quote', buggyQuoteBody($trip, ['units' => [['unit_option_id' => $trip->unitOptions[1]->id, 'quantity' => 1]]]))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The selected units have 2 seats for 3 travellers. Add another unit.')
        ->assertJsonPath('errors.units.0', 'The selected units have 2 seats for 3 travellers. Add another unit.')
        ->assertJsonPath('data.valid', false);
});

test('a per person quote above the maximum persons is rejected', function () {
    snorkellingTrip();

    $this->postJson('/api/v1/trips/ras-mohammed-snorkelling/quote', ['booking_type' => 'group', 'travel_date' => '2026-11-25', 'adults' => 13])
        ->assertUnprocessable()
        ->assertJsonPath('errors.adults.0', 'This trip allows at most 12 persons per booking.');
});

test('the request body is validated', function () {
    buggyTrip();

    $this->postJson('/api/v1/trips/desert-buggy-safari/quote', ['travel_date' => '25/11/2026', 'adults' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['booking_type', 'travel_date', 'adults']);
});

test('partners receive partner totals in the quote', function () {
    $trip = buggyTrip();
    $partner = Partner::factory()->create(['default_commission' => 10]);
    Sanctum::actingAs($partner->user);

    $this->postJson('/api/v1/trips/desert-buggy-safari/quote', buggyQuoteBody($trip))
        ->assertOk()
        ->assertJsonPath('data.retail_total', 85)
        ->assertJsonPath('data.partner_total', 76.5)
        ->assertJsonPath('data.commission_amount', 8.5)
        ->assertJsonPath('data.price_rule', 'default_commission')
        ->assertJsonPath('data.total', 76.5);
});

test('quoting an unknown trip returns 404', function () {
    $this->postJson('/api/v1/trips/unknown/quote', ['booking_type' => 'group', 'travel_date' => '2026-11-25', 'adults' => 1])->assertNotFound();
});
