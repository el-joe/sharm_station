<?php

namespace Database\Factories;

use App\Enums\BookingType;
use App\Models\Trip;
use App\Models\TripAvailabilityRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripAvailabilityRule>
 */
class TripAvailabilityRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'booking_type' => BookingType::Group,
            'weekdays' => [1, 2, 3, 4, 5, 6, 7],
            'dates' => [],
            'blackout_dates' => [],
            'lead_time_hours' => 24,
        ];
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => ['booking_type' => BookingType::Private, 'weekdays' => null]);
    }
}
