<?php

namespace App\Services\Transfers;

use App\DTOs\Transfer\RouteData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Driving distance and time from the Google Routes API (computeRoutes, travelMode DRIVE).
 *
 * Without a server key, on an HTTP error, a timeout or an empty route it falls back to the
 * Haversine estimate, which is flagged as estimated so the admin reviews the request (SRS 5.2).
 */
class GoogleRoutesDistanceProvider implements DistanceProvider
{
    public const NAME = 'google_routes';

    public const FIELD_MASK = 'routes.distanceMeters,routes.duration';

    public function __construct(
        private readonly HaversineDistanceProvider $fallback,
        private readonly ?string $apiKey,
        private readonly string $endpoint = 'https://routes.googleapis.com/directions/v2:computeRoutes',
        private readonly int $timeoutSeconds = 5,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): RouteData
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            return $this->fallback->route($fromLat, $fromLng, $toLat, $toLng);
        }

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->connectTimeout($this->timeoutSeconds)
                ->acceptJson()
                ->withHeaders([
                    'X-Goog-Api-Key' => $this->apiKey,
                    'X-Goog-FieldMask' => self::FIELD_MASK,
                ])
                ->post($this->endpoint, [
                    'origin' => ['location' => ['latLng' => ['latitude' => $fromLat, 'longitude' => $fromLng]]],
                    'destination' => ['location' => ['latLng' => ['latitude' => $toLat, 'longitude' => $toLng]]],
                    'travelMode' => 'DRIVE',
                    'units' => 'METRIC',
                ]);

            if ($response->failed()) {
                return $this->fallBack($fromLat, $fromLng, $toLat, $toLng, 'HTTP '.$response->status());
            }

            $route = $response->json('routes.0');
            $meters = is_array($route) ? ($route['distanceMeters'] ?? null) : null;

            if (! is_numeric($meters) || $meters <= 0) {
                return $this->fallBack($fromLat, $fromLng, $toLat, $toLng, 'empty route');
            }

            return new RouteData(
                distanceKm: round($meters / 1000, 2),
                durationMinutes: (int) max(1, ceil(self::seconds((string) ($route['duration'] ?? '0s')) / 60)),
                provider: self::NAME,
                estimated: false,
            );
        } catch (Throwable $exception) {
            return $this->fallBack($fromLat, $fromLng, $toLat, $toLng, $exception->getMessage());
        }
    }

    /**
     * Routes API durations are protobuf strings such as "1234s" or "1234.5s".
     */
    public static function seconds(string $duration): float
    {
        return (float) rtrim(trim($duration), 's');
    }

    private function fallBack(float $fromLat, float $fromLng, float $toLat, float $toLng, string $reason): RouteData
    {
        Log::warning('Google Routes API unavailable, using the Haversine estimate.', ['reason' => $reason]);

        return $this->fallback->route($fromLat, $fromLng, $toLat, $toLng);
    }
}
