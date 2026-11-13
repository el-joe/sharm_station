<?php

use App\Enums\AddonPricingBasis;
use App\Enums\BookingStatus;
use App\Enums\DiscountType;
use App\Enums\PackagePriceMode;
use App\Enums\ServiceType;
use App\Enums\TransferStatus;
use App\Enums\VehicleType;
use App\Models\Addon;
use App\Models\Booking;
use App\Models\Destination;
use App\Models\Package;
use App\Models\Partner;
use App\Models\SeasonPrice;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    seedCurrencies();
});

/**
 * Two stages (Cairo 3 days, Luxor 2 days), one trip per stage, two included transfers, adult 990 / child 690.
 */
function classicPackage(array $attributes = []): Package
{
    $cairo = Destination::factory()->create(['name' => ['en' => 'Cairo'], 'slug' => 'cairo']);
    $luxor = Destination::factory()->create(['name' => ['en' => 'Luxor'], 'slug' => 'luxor']);
    $pyramids = Trip::factory()->create(['destination_id' => $cairo->id, 'slug' => 'pyramids', 'base_price' => 60, 'base_child_price' => 30, 'from_price' => 60]);
    $karnak = Trip::factory()->create(['destination_id' => $luxor->id, 'slug' => 'karnak', 'base_price' => 40, 'base_child_price' => 20, 'from_price' => 40]);

    $package = Package::factory()->create([
        'destination_id' => $cairo->id,
        'slug' => 'classic-egypt',
        'days' => 5,
        'nights' => 4,
        'min_persons' => 1,
        ...$attributes,
    ]);

    $first = $package->stages()->create(['destination_id' => $cairo->id, 'position' => 1, 'days' => 3, 'nights' => 3]);
    $second = $package->stages()->create(['destination_id' => $luxor->id, 'position' => 2, 'days' => 2, 'nights' => 1]);
    $first->stageTrips()->create(['trip_id' => $pyramids->id, 'day_number' => 2]);
    $second->stageTrips()->create(['trip_id' => $karnak->id, 'day_number' => 1]);

    $package->includedTransfers()->create(['package_stage_id' => $first->id, 'from_place' => 'Cairo airport', 'to_place' => 'Cairo hotel', 'day_number' => 1, 'time' => '10:00', 'vehicle_type' => VehicleType::Minivan]);
    $package->includedTransfers()->create(['package_stage_id' => $second->id, 'from_place' => 'Luxor hotel', 'to_place' => 'Luxor airport', 'day_number' => 5, 'time' => '08:00', 'vehicle_type' => VehicleType::Sedan]);

    return $package->fresh();
}

function packageBookingBody(array $overrides = []): array
{
    return [
        'service' => 'package',
        'package_slug' => 'classic-egypt',
        'booking_type' => 'group',
        'travel_date' => '2026-11-25',
        'adults' => 2,
        'lead' => ['name' => 'Giulia Rossi', 'email' => 'giulia@example.com'],
        'travelers' => [
            ['first_name' => 'Giulia', 'last_name' => 'Rossi', 'type' => 'adult'],
            ['first_name' => 'Paolo', 'last_name' => 'Rossi', 'type' => 'adult'],
        ],
        ...$overrides,
    ];
}

test('packages are listed with from price, stages and the transfers included flag', function () {
    classicPackage();
    Package::factory()->create(['slug' => 'cheap', 'from_price' => 400, 'base_price' => 400]);
    Package::factory()->draft()->create();

    $this->getJson('/api/v1/packages?sort=price_asc')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.slug', 'cheap')
        ->assertJsonPath('data.1.transfers_included', true)
        ->assertJsonPath('data.1.stages.1.destination_slug', 'luxor');
});

test('packages can be filtered by stage destination and price in the requested currency', function () {
    classicPackage();
    Package::factory()->create(['slug' => 'red-sea', 'from_price' => 400, 'base_price' => 400]);

    $this->getJson('/api/v1/packages?destination=luxor')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'classic-egypt');
    $this->getJson('/api/v1/packages?currency=USD&price_max=500')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'red-sea');
});

test('the package detail has stages, days, linked trips, included transfers, timeline and price list', function () {
    $package = classicPackage();
    SeasonPrice::factory()->create(['priceable_type' => 'package', 'priceable_id' => $package->id, 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-31', 'adult_price' => 1100, 'child_price' => 800]);

    $this->getJson('/api/v1/packages/classic-egypt')
        ->assertOk()
        ->assertJsonPath('data.transfers_included', true)
        ->assertJsonPath('data.stages.1.start_day', 4)
        ->assertJsonPath('data.stages.1.trips.0.day_number', 4)
        ->assertJsonPath('data.stages.1.trips.0.trip.slug', 'karnak')
        ->assertJsonCount(2, 'data.trips')
        ->assertJsonPath('data.included_transfers.0.from', 'Cairo airport')
        ->assertJsonPath('data.included_transfers.0.transfer_included', true)
        ->assertJsonCount(5, 'data.itinerary')
        ->assertJsonPath('data.itinerary.0.items.0.transfer_included', true)
        ->assertJsonPath('data.itinerary.1.items.0.trip_slug', 'pyramids')
        ->assertJsonPath('data.price_list.0.prices.adult.amount_eur', 1100)
        ->assertJsonPath('data.from_price.amount_eur', 990);

    $this->getJson('/api/v1/packages/unknown')->assertNotFound();
});

test('a fixed package quote applies the season of the travel date, child bands and add-ons', function () {
    $package = classicPackage();
    SeasonPrice::factory()->create(['priceable_type' => 'package', 'priceable_id' => $package->id, 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-31', 'adult_price' => 1100, 'child_price' => 800]);
    $policy = $package->childPolicy()->create(['children_allowed' => true, 'adult_from_age' => 12]);
    $policy->bands()->create(['min_age' => 0, 'max_age' => 5, 'discount_type' => DiscountType::Percent, 'discount_value' => 50]);
    $addon = Addon::factory()->basis(AddonPricingBasis::PerBooking, 80)->create();
    $package->addons()->attach($addon->id, ['sort' => 0]);

    $this->postJson('/api/v1/packages/classic-egypt/quote', [
        'booking_type' => 'private', 'travel_date' => '2026-12-10', 'adults' => 2, 'children' => 2, 'child_ages' => [4, 9],
        'addons' => [['addon_id' => $addon->id]],
    ])
        ->assertOk()
        ->assertJsonPath('data.season_name', 'High season')
        ->assertJsonPath('data.lines.0.total', 2200)
        ->assertJsonPath('data.lines.1.unit_price', 550)
        ->assertJsonPath('data.lines.1.meta.age', 4)
        ->assertJsonPath('data.lines.2.unit_price', 800)
        ->assertJsonPath('data.lines.3.total', 80)
        ->assertJsonPath('data.total', 3630)
        ->assertJsonPath('data.deposit_amount', 1089)
        ->assertJsonPath('data.end_date', '2026-12-14')
        ->assertJsonPath('data.included_transfers.1.date', '2026-12-14')
        ->assertJsonPath('data.transfers_included', true);
});

test('a computed package sums the linked trips minus the discount', function () {
    classicPackage(['price_mode' => PackagePriceMode::Computed, 'base_price' => null, 'discount_percent' => 10]);

    $this->postJson('/api/v1/packages/classic-egypt/quote', ['booking_type' => 'group', 'travel_date' => '2026-11-25', 'adults' => 2])
        ->assertOk()
        ->assertJsonPath('data.price_mode', 'computed')
        ->assertJsonPath('data.lines.0.unit_price', 90)
        ->assertJsonPath('data.total', 180);
});

test('package quotes validate party size, booking type and date', function () {
    classicPackage(['max_persons' => 2, 'private_enabled' => false]);

    $this->postJson('/api/v1/packages/classic-egypt/quote', ['booking_type' => 'private', 'travel_date' => '2026-11-13', 'adults' => 3])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['booking_type', 'travel_date', 'adults']);
});

test('partners get the partner total on package quotes', function () {
    classicPackage(['partner_price' => 900]);
    $partner = Partner::factory()->create(['default_commission' => 10]);
    Sanctum::actingAs($partner->user);

    $this->postJson('/api/v1/packages/classic-egypt/quote', ['booking_type' => 'group', 'travel_date' => '2026-11-25', 'adults' => 2])
        ->assertOk()
        ->assertJsonPath('data.total', 1800)
        ->assertJsonPath('data.partner_total', 1800)
        ->assertJsonPath('data.retail_total', 1980)
        ->assertJsonPath('data.price_rule', 'partner_price');
});

test('a package booking creates included-transfer tasks without price', function () {
    classicPackage();

    $this->postJson('/api/v1/bookings', packageBookingBody())
        ->assertCreated()
        ->assertJsonPath('data.service_type', 'package')
        ->assertJsonPath('data.status', 'awaiting_payment')
        ->assertJsonPath('data.totals.total', 1980)
        ->assertJsonCount(2, 'data.included_transfers')
        ->assertJsonPath('data.included_transfers.1.date', '2026-11-29');

    $booking = Booking::query()->sole();

    expect($booking->service_type)->toBe(ServiceType::Package)
        ->and($booking->status)->toBe(BookingStatus::AwaitingPayment)
        ->and($booking->includedTransfers)->toHaveCount(2)
        ->and($booking->includedTransfers->first()->status)->toBe(TransferStatus::Pending)
        ->and($booking->includedTransfers->first()->passengers)->toBe(2)
        ->and($booking->includedTransfers->first()->getAttributes())->not->toHaveKey('price');
});

test('a package booking is rejected when the travellers do not match', function () {
    classicPackage();

    $this->postJson('/api/v1/bookings', packageBookingBody(['adults' => 3]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['travelers']);

    $this->postJson('/api/v1/bookings', packageBookingBody(['package_slug' => 'missing']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['package_slug']);
});
