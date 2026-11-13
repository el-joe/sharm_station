<?php

use App\DTOs\Pricing\QuoteRequestData;
use App\Enums\BookingType;
use App\Enums\DiscountType;
use App\Enums\UserRole;
use App\Models\Currency;
use App\Models\Trip;
use App\Models\TripAvailabilityRule;
use App\Models\TripUnitOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Domain helpers shared by the pricing, booking and API tests
|--------------------------------------------------------------------------
*/

/**
 * Per-unit buggy trip (Single 1 seat EUR 35, Double 2 seats EUR 50, Family 4 seats EUR 90), bookable every day.
 */
function buggyTrip(array $attributes = []): Trip
{
    $trip = Trip::factory()->perUnit()->privateAndGroup()->create([
        'title' => ['en' => 'Desert buggy safari', 'it' => 'Safari in buggy'],
        'slug' => 'desert-buggy-safari',
        ...$attributes,
    ]);

    TripUnitOption::factory()->for($trip)->create(['name' => ['en' => 'Single buggy', 'it' => 'Buggy singolo'], 'seats' => 1, 'price' => 35, 'max_quantity' => 4, 'children_allowed' => false, 'sort' => 0]);
    TripUnitOption::factory()->for($trip)->create(['name' => ['en' => 'Double buggy', 'it' => 'Buggy doppio'], 'seats' => 2, 'price' => 50, 'max_quantity' => 10, 'sort' => 1]);
    TripUnitOption::factory()->for($trip)->create(['name' => ['en' => 'Family buggy', 'it' => 'Buggy familiare'], 'seats' => 4, 'price' => 90, 'max_quantity' => 5, 'sort' => 2]);

    TripAvailabilityRule::factory()->for($trip)->create(['booking_type' => BookingType::Group, 'lead_time_hours' => 12]);
    TripAvailabilityRule::factory()->for($trip)->private()->create(['lead_time_hours' => 12]);

    return $trip->fresh();
}

/**
 * Per-person snorkelling trip: group adult EUR 25 / child EUR 15, max 12 persons, private tiers, child bands 0-3 free and 4-11 50% off.
 */
function snorkellingTrip(array $attributes = []): Trip
{
    $trip = Trip::factory()->privateAndGroup()->create([
        'title' => ['en' => 'Ras Mohammed snorkelling', 'it' => 'Snorkeling a Ras Mohammed'],
        'slug' => 'ras-mohammed-snorkelling',
        'base_price' => 25,
        'base_child_price' => 15,
        'min_persons' => 1,
        'max_persons' => 12,
        ...$attributes,
    ]);

    $trip->privateTiers()->createMany([
        ['min_people' => 1, 'max_people' => 2, 'price_per_person' => 70],
        ['min_people' => 3, 'max_people' => 4, 'price_per_person' => 55],
        ['min_people' => 5, 'max_people' => 8, 'price_per_person' => 45],
    ]);

    $policy = $trip->childPolicy()->create(['children_allowed' => true, 'adult_from_age' => 12]);
    $policy->bands()->createMany([
        ['min_age' => 0, 'max_age' => 3, 'discount_type' => DiscountType::Free],
        ['min_age' => 4, 'max_age' => 11, 'discount_type' => DiscountType::Percent, 'discount_value' => 50],
    ]);

    TripAvailabilityRule::factory()->for($trip)->create(['booking_type' => BookingType::Group, 'lead_time_hours' => 24]);
    TripAvailabilityRule::factory()->for($trip)->private()->create(['lead_time_hours' => 24]);

    return $trip->fresh();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function quoteRequest(array $overrides = [], mixed $partner = null, string $currency = 'EUR'): QuoteRequestData
{
    return QuoteRequestData::fromArray([
        'booking_type' => 'group',
        'travel_date' => '2026-11-25',
        'adults' => 2,
        'children' => 0,
        ...$overrides,
    ], $partner, $currency);
}

function seedCurrencies(): void
{
    Currency::factory()->create();
    Currency::factory()->usd()->create();
    Currency::factory()->create(['code' => 'EGP', 'name' => 'Egyptian pound', 'symbol' => 'E£', 'rate_to_eur' => 50, 'margin_percent' => 2, 'sort' => 3]);
}

/*
|--------------------------------------------------------------------------
| Admin panel helpers
|--------------------------------------------------------------------------
*/

function staffUser(UserRole $role = UserRole::SuperAdmin): User
{
    return User::factory()->role($role)->create();
}
