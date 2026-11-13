<?php

use App\DTOs\Transfer\RouteData;
use App\Enums\ServiceType;
use App\Enums\TransferStatus;
use App\Models\Booking;
use App\Models\Partner;
use App\Models\TransferFixedRoute;
use App\Models\TransferHub;
use App\Models\TransferRequest;
use App\Models\VehicleType;
use App\Services\Transfers\DistanceProvider;
use App\Services\Transfers\HaversineDistanceProvider;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    seedCurrencies();

    $this->hub = TransferHub::factory()->create(['slug' => 'ssh-airport']);
    $this->sedan = VehicleType::factory()->create();
    $this->minivan = VehicleType::factory()->minivan()->create();

    // 50 km road distance for every route: tiers 20 × 1.20 + 30 × 0.90 = 51.
    $this->app->instance(DistanceProvider::class, new class implements DistanceProvider
    {
        public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): RouteData
        {
            return new RouteData(50, 50, 'test');
        }
    });
});

function transferBody(array $overrides = []): array
{
    return [
        'hub_id' => test()->hub->id,
        'vehicle_type_id' => test()->sedan->id,
        'destination' => ['name' => 'Sharks Bay hotel', 'lat' => 27.99, 'lng' => 34.42],
        'pickup_at' => '2026-11-20 10:00',
        'passengers' => 2,
        'luggage' => 2,
        ...$overrides,
    ];
}

test('hubs and vehicles are listed for the transfer form', function () {
    TransferFixedRoute::factory()->create(['transfer_hub_id' => $this->hub->id]);
    TransferHub::factory()->create(['is_enabled' => false]);

    $this->getJson('/api/v1/transfers/hubs')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'ssh-airport')
        ->assertJsonPath('data.0.places.0.name', 'Naama Bay')
        ->assertJsonPath('data.0.extras.0.code', 'child_seat')
        ->assertJsonMissingPath('data.0.distance_tiers');

    $this->getJson('/api/v1/transfers/vehicles?locale=it')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Berlina')
        ->assertJsonPath('data.1.capacity', 7);
});

test('the distance formula applies tiers, base fare, multiplier, minimum and fees', function () {
    $this->postJson('/api/v1/transfers/quote', transferBody())
        ->assertOk()
        ->assertJsonPath('data.service_type', 'transfer')
        ->assertJsonPath('data.distance_km', 50)
        ->assertJsonPath('data.lines.0.type', 'transfer')
        ->assertJsonPath('data.lines.0.meta.breakdown.distance_charge', 51)
        ->assertJsonPath('data.lines.0.meta.breakdown.subtotal', 61)
        ->assertJsonPath('data.total', 66)
        ->assertJsonPath('data.deposit_amount', 66);

    // Minivan multiplier 1.4: 61 × 1.4 = 85.40 + 5 fees = 90.40, rounded up to 91.
    $this->postJson('/api/v1/transfers/quote', transferBody(['vehicle_type_id' => $this->minivan->id]))
        ->assertOk()
        ->assertJsonPath('data.total', 91);
});

test('night surcharge, round trip discount and extras are added', function () {
    $this->postJson('/api/v1/transfers/quote', transferBody([
        'pickup_at' => '2026-11-20 23:30',
        'round_trip' => true,
        'return_at' => '2026-11-27 10:00',
        'extras' => ['child_seat'],
    ]))
        ->assertOk()
        ->assertJsonPath('data.lines.0.total', 74)
        ->assertJsonPath('data.lines.1.total', 60)
        ->assertJsonPath('data.lines.1.meta.leg', 'return')
        ->assertJsonPath('data.lines.2.type', 'extra')
        ->assertJsonPath('data.total', 139);
});

test('the minimum fare applies to short rides', function () {
    $this->app->instance(DistanceProvider::class, new class implements DistanceProvider
    {
        public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): RouteData
        {
            return new RouteData(5, 6, 'test');
        }
    });

    // 10 + 5 × 1.20 = 16 < minimum 25, + 5 fees = 30.
    $this->postJson('/api/v1/transfers/quote', transferBody())
        ->assertOk()
        ->assertJsonPath('data.lines.0.meta.breakdown.minimum_applied', true)
        ->assertJsonPath('data.total', 30);
});

test('a fixed route overrides the distance formula and carries its partner price', function () {
    TransferFixedRoute::factory()->create(['transfer_hub_id' => $this->hub->id, 'lat' => 27.99, 'lng' => 34.42, 'price' => 35, 'partner_price' => 28]);
    $partner = Partner::factory()->create(['default_commission' => 10]);
    Sanctum::actingAs($partner->user);

    $this->postJson('/api/v1/transfers/quote', transferBody(['vehicle_type_id' => $this->minivan->id]))
        ->assertOk()
        ->assertJsonPath('data.lines.0.meta.breakdown.method', 'fixed_route')
        ->assertJsonPath('data.retail_total', 49)
        ->assertJsonPath('data.partner_total', 39.2)
        ->assertJsonPath('data.price_rule', 'partner_price');
});

test('transfer quotes validate capacity, service area, notice and return', function () {
    $this->postJson('/api/v1/transfers/quote', transferBody([
        'passengers' => 5,
        'destination' => ['name' => 'Cairo', 'lat' => 30.04, 'lng' => 31.23],
        'pickup_at' => '2026-11-13 12:00',
        'round_trip' => true,
        'return_at' => '2026-11-13 11:00',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['passengers', 'destination', 'pickup_at', 'return_at']);

    $this->postJson('/api/v1/transfers/quote', transferBody(['hub_id' => 999]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['hub_id']);
});

test('the haversine provider estimates the road distance with a 1.3 factor', function () {
    $route = (new HaversineDistanceProvider(1.3, 60))->route(27.9773, 34.3950, 27.9158, 34.3299);
    $straight = HaversineDistanceProvider::straightLineKm(27.9773, 34.3950, 27.9158, 34.3299);

    expect($straight)->toBeGreaterThan(9)->toBeLessThan(10)
        ->and($route->distanceKm)->toBe(round($straight * 1.3, 2))
        ->and($route->estimated)->toBeTrue();
});

test('a transfer booking stores the request with its distance and breakdown', function () {
    $this->postJson('/api/v1/bookings', [
        'service' => 'transfer',
        'transfer' => transferBody(['flight_number' => 'AZ 896']),
        'lead' => ['name' => 'Giulia Rossi', 'email' => 'giulia@example.com', 'phone' => '+39 333 1234567'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.service_type', 'transfer')
        ->assertJsonPath('data.totals.total', 66)
        ->assertJsonPath('data.transfer.flight_number', 'AZ 896')
        ->assertJsonPath('data.transfer.status', 'awaiting_payment');

    $request = TransferRequest::query()->sole();

    expect(Booking::query()->sole()->service_type)->toBe(ServiceType::Transfer)
        ->and($request->status)->toBe(TransferStatus::AwaitingPayment)
        ->and((float) $request->distance_km)->toBe(50.0)
        ->and((float) $request->price)->toBe(66.0)
        ->and($request->breakdown[0]['breakdown']['distance_charge'])->toBe(51);
});

test('a transfer booking with an invalid quote is rejected', function () {
    $this->postJson('/api/v1/bookings', [
        'transfer' => transferBody(['passengers' => 9]),
        'lead' => ['name' => 'Giulia Rossi', 'email' => 'giulia@example.com', 'phone' => '+39 333 1234567'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['passengers']);

    expect(Booking::query()->count())->toBe(0);
});
