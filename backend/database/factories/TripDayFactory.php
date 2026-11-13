<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\TripDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripDay>
 */
class TripDayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'day_number' => 1,
            'title' => ['en' => 'Day 1', 'it' => 'Giorno 1'],
            'description' => ['en' => fake()->sentence(), 'it' => fake()->sentence()],
        ];
    }
}
