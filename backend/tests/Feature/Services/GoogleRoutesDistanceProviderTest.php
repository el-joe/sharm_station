<?php

use App\Services\Transfers\DistanceProvider;
use App\Services\Transfers\GoogleRoutesDistanceProvider;
use App\Services\Transfers\HaversineDistanceProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const ROUTES_URL = 'https://routes.googleapis.com/directions/v2:computeRoutes';

function googleRoutes(?string $key = 'test-server-key'): GoogleRoutesDistanceProvider
{
    return new GoogleRoutesDistanceProvider(new HaversineDistanceProvider, $key, ROUTES_URL, 5);
}

test('the Routes API distance and duration are returned as km and minutes', function () {
    Http::fake([ROUTES_URL => Http::response(['routes' => [['distanceMeters' => 42512, 'duration' => '1830s']]])]);

    $route = googleRoutes()->route(27.9779, 34.3950, 27.9158, 34.3300);

    expect($route->distanceKm)->toBe(42.51)
        ->and($route->durationMinutes)->toBe(31)
        ->and($route->provider)->toBe('google_routes')
        ->and($route->estimated)->toBeFalse();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->hasHeader('X-Goog-Api-Key', 'test-server-key')
        && $request->hasHeader('X-Goog-FieldMask', 'routes.distanceMeters,routes.duration')
        && $request['travelMode'] === 'DRIVE'
        && $request['origin']['location']['latLng'] === ['latitude' => 27.9779, 'longitude' => 34.395]
        && $request['destination']['location']['latLng'] === ['latitude' => 27.9158, 'longitude' => 34.33]);
});

test('it falls back to the flagged Haversine estimate when Google fails', function (Closure $fake) {
    Http::fake([ROUTES_URL => $fake]);

    $route = googleRoutes()->route(27.9779, 34.3950, 27.9158, 34.3300);
    $expected = (new HaversineDistanceProvider)->route(27.9779, 34.3950, 27.9158, 34.3300);

    expect($route->provider)->toBe('haversine')
        ->and($route->estimated)->toBeTrue()
        ->and($route->distanceKm)->toBe($expected->distanceKm);
})->with([
    'HTTP error' => fn () => fn () => Http::response(['error' => ['code' => 403]], 403),
    'empty route' => fn () => fn () => Http::response([]),
    'zero distance' => fn () => fn () => Http::response(['routes' => [['duration' => '0s']]]),
    'timeout' => fn () => fn () => throw new ConnectionException('cURL error 28: Operation timed out'),
]);

test('without a server key Google is never called', function () {
    Http::fake();

    expect(googleRoutes(null)->route(27.9779, 34.3950, 27.9158, 34.3300)->provider)->toBe('haversine');
    Http::assertNothingSent();
});

test('the container binds Google only when the server key is set', function (?string $key, string $driver, string $expected) {
    config(['services.google.maps_server_key' => $key, 'transfers.distance_driver' => $driver]);

    expect(app(DistanceProvider::class))->toBeInstanceOf($expected);
})->with([
    'no key' => [null, 'auto', HaversineDistanceProvider::class],
    'key' => ['test-server-key', 'auto', GoogleRoutesDistanceProvider::class],
    'forced Haversine' => ['test-server-key', 'haversine', HaversineDistanceProvider::class],
]);
