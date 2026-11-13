<?php

namespace Database\Factories;

use App\Models\TripDay;
use App\Models\TripTimelineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripTimelineItem>
 */
class TripTimelineItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_day_id' => TripDay::factory(),
            'time_from' => '08:00',
            'time_to' => '09:00',
            'title' => ['en' => 'Hotel pick-up', 'it' => 'Prelievo in hotel'],
            'description' => null,
            'place' => null,
            'sort' => 0,
        ];
    }
}
