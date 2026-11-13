<?php

namespace App\Services\Transfers;

use App\DTOs\Transfer\RouteData;

/**
 * Road distance between two coordinates (Google Routes, Mapbox, OSRM... or the Haversine fallback).
 */
interface DistanceProvider
{
    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): RouteData;
}
