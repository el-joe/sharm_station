<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\TripMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripMedia>
 */
class TripMediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'path' => '/images/placeholders/trip-2.jpg',
            'alt' => ['en' => 'Trip photo', 'it' => 'Foto escursione'],
            'sort' => 0,
        ];
    }
}
