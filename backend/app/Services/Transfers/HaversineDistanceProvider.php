<?php

namespace App\Services\Transfers;

use App\DTOs\Transfer\RouteData;

/**
 * Offline estimate: great-circle distance × road factor, at an average speed. Needs no API key.
 * Results are flagged as estimated so the admin can review them (SRS 5.2 quality controls).
 */
class HaversineDistanceProvider implements DistanceProvider
{
    public const EARTH_RADIUS_KM = 6371.0;

    public function __construct(
        private readonly float $roadFactor = 1.3,
        private readonly float $averageSpeedKmh = 60.0,
    ) {}

    public function name(): string
    {
        return 'haversine';
    }

    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): RouteData
    {
        $distance = round(self::straightLineKm($fromLat, $fromLng, $toLat, $toLng) * $this->roadFactor, 2);

        return new RouteData(
            distanceKm: $distance,
            durationMinutes: (int) max(1, ceil($distance / $this->averageSpeedKmh * 60)),
            provider: 'haversine',
            estimated: true,
        );
    }

    public static function straightLineKm(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $dLat = deg2rad($toLat - $fromLat);
        $dLng = deg2rad($toLng - $fromLng);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
