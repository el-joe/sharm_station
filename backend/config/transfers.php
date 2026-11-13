<?php

return [
    /*
    | Distance provider used by the transfer pricing (SRS 5.2). Only "haversine" ships today; a maps
    | provider (Google Routes, Mapbox, OSRM) can be added by implementing App\Services\Transfers\DistanceProvider.
    */
    'distance_driver' => env('TRANSFER_DISTANCE_DRIVER', 'haversine'),
    'road_factor' => (float) env('TRANSFER_ROAD_FACTOR', 1.3),
    'average_speed_kmh' => (float) env('TRANSFER_AVERAGE_SPEED_KMH', 60),

    /* Leg prices are rounded up to this step in EUR (0 = no rounding). */
    'rounding_step' => (float) env('TRANSFER_ROUNDING_STEP', 1),

    /* Night surcharge window (pickup time, 24h). */
    'night_from' => '22:00',
    'night_to' => '06:00',

    /* How long a quote stays valid. */
    'quote_validity_minutes' => (int) env('TRANSFER_QUOTE_VALIDITY_MINUTES', 60),
];
