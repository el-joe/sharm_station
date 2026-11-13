<?php

namespace App\DTOs\Transfer;

/**
 * Road distance and travel time between two points. $estimated is true when a fallback estimate was used.
 */
final readonly class RouteData
{
    public function __construct(
        public float $distanceKm,
        public int $durationMinutes,
        public string $provider,
        public bool $estimated = false,
    ) {}
}
