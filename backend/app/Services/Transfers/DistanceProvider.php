<?php

namespace App\Services\Transfers;

use App\DTOs\Transfer\RouteData;

/**
 * Road distance between two coordinates (Google Routes, or the Haversine fallback).
 */
interface DistanceProvider
{
    /**
     * Configured provider name ("google_routes", "haversine"...), used in the route cache key.
     */
    public function name(): string;

    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): RouteData;
}
