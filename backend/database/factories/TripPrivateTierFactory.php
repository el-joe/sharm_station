<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\TripPrivateTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripPrivateTier>
 */
class TripPrivateTierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory()->privateOnly(),
            'min_people' => 1,
            'max_people' => 2,
            'price_per_person' => 60,
        ];
    }
}
