<?php

use App\Models\TransferHub;
use App\Models\TransferRequest;
use App\Models\VehicleType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    seedCurrencies();
    $this->hub = TransferHub::factory()->create();
    $this->sedan = VehicleType::factory()->create();
    $this->body = [
        'hub_id' => $this->hub->id,
        'vehicle_type_id' => $this->sedan->id,
        'destination' => ['name' => 'Sharks Bay hotel', 'lat' => 27.99, 'lng' => 34.42],
        'pickup_at' => '2026-11-20 10:00',
        'passengers' => 2,
        'luggage' => 2,
    ];
});

test('without a Google key the quote uses the estimated Haversine distance', function () {
    Http::fake();

    $this->postJson('/api/v1/transfers/quote', $this->body)
        ->assertOk()
        ->assertJsonPath('data.distance_provider', 'haversine')
        ->assertJsonPath('data.distance_estimated', true);

    Http::assertNothingSent();
});

test('with a server key the quote and the stored request use Google Routes distances', function () {
    config(['services.google.maps_server_key' => 'test-server-key']);
    Http::fake(['routes.googleapis.com/*' => Http::response(['routes' => [['distanceMeters' => 50000, 'duration' => '3000s']]])]);

    $this->postJson('/api/v1/transfers/quote', $this->body)
        ->assertOk()
        ->assertJsonPath('data.distance_km', 50)
        ->assertJsonPath('data.duration_minutes', 50)
        ->assertJsonPath('data.distance_provider', 'google_routes')
        ->assertJsonPath('data.distance_estimated', false);

    $this->postJson('/api/v1/bookings', [
        'service' => 'transfer',
        'transfer' => $this->body,
        'nationality_code' => 'IT',
        'lead' => ['name' => 'Giulia Rossi', 'email' => 'giulia@example.com', 'phone' => '+39 333 1234567'],
    ])->assertCreated();

    $request = TransferRequest::query()->sole();
    expect($request->distance_provider)->toBe('google_routes')->and($request->needs_review)->toBeFalse();
    Http::assertSentCount(1);
});

test('when Google fails the request is flagged for admin review', function () {
    config(['services.google.maps_server_key' => 'test-server-key']);
    Http::fake(['routes.googleapis.com/*' => Http::response([], 500)]);

    $this->postJson('/api/v1/bookings', [
        'service' => 'transfer',
        'transfer' => $this->body,
        'nationality_code' => 'IT',
        'lead' => ['name' => 'Giulia Rossi', 'email' => 'giulia@example.com', 'phone' => '+39 333 1234567'],
    ])->assertCreated();

    $request = TransferRequest::query()->sole();
    expect($request->distance_provider)->toBe('haversine')->and($request->needs_review)->toBeTrue();
});
