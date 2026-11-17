<?php

use App\Models\ProductPrice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
});

test('the nationalities endpoint lists every country with its group and the detected country', function () {
    $response = $this->getJson('/api/v1/nationalities', ['CF-IPCountry' => 'EG'])
        ->assertOk()
        ->assertJsonPath('detected', 'EG')
        ->assertJsonPath('detected_group.code', 'egyptian')
        ->assertJsonPath('default_group.code', 'rest_of_world')
        ->assertJsonCount(4, 'groups');

    $countries = collect($response->json('data'))->keyBy('code');

    expect($countries)->toHaveCount(249)
        ->and($countries['EG']['group']['code'])->toBe('egyptian')
        ->and($countries['IT']['name'])->toBe('Italy')
        ->and($countries['US']['group']['code'])->toBe('rest_of_world');

    $this->getJson('/api/v1/nationalities?locale=it')->assertOk()->assertJsonPath('detected', null);
    expect(collect($this->getJson('/api/v1/nationalities?locale=it')->json('data'))->firstWhere('code', 'EG')['name'])->toBe('Egitto');
});

test('priced endpoints follow ?nationality=, the header or the profile, with the default group as fallback', function () {
    $trip = snorkellingTrip(['base_price' => 45, 'from_price' => 45]);
    ProductPrice::factory()->create(['priceable_type' => 'trip', 'priceable_id' => $trip->id, 'nationality_group_id' => nationalityGroup('egyptian')->id, 'adult_price' => 20, 'child_price' => 10]);

    $this->getJson('/api/v1/trips/ras-mohammed-snorkelling?nationality=EG')
        ->assertOk()
        ->assertJsonPath('data.from_price.amount_eur', 20)
        ->assertJsonPath('data.group_prices.adult.amount_eur', 20);
    $this->getJson('/api/v1/trips/ras-mohammed-snorkelling?nationality=ZZ')->assertJsonPath('data.group_prices.adult.amount_eur', 45);
    $this->getJson('/api/v1/trips/ras-mohammed-snorkelling')->assertJsonPath('data.group_prices.adult.amount_eur', 45);
    $this->getJson('/api/v1/trips/ras-mohammed-snorkelling', ['X-Nationality' => 'EG'])->assertJsonPath('data.group_prices.adult.amount_eur', 20);
    $this->getJson('/api/v1/trips?nationality=EG')->assertOk()->assertJsonPath('data.0.from_price.amount_eur', 20);

    $this->postJson('/api/v1/trips/ras-mohammed-snorkelling/quote?nationality=EG', ['booking_type' => 'group', 'travel_date' => '2026-11-25', 'adults' => 2])
        ->assertOk()
        ->assertJsonPath('data.total', 40)
        ->assertJsonPath('data.nationality.group.code', 'egyptian');

    $user = User::factory()->create(['nationality_code' => 'EG']);
    Sanctum::actingAs($user);
    $this->getJson('/api/v1/trips/ras-mohammed-snorkelling')->assertJsonPath('data.group_prices.adult.amount_eur', 20);
});

test('customers save their nationality in the profile', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $this->patchJson('/api/v1/me/profile', ['nationality_code' => 'eg'])->assertOk()->assertJsonPath('data.nationality_code', 'EG');
    expect($user->fresh()->nationality_code)->toBe('EG');

    $this->patchJson('/api/v1/me/profile', ['nationality_code' => 'XX'])->assertUnprocessable()->assertJsonValidationErrors(['nationality_code']);
});
