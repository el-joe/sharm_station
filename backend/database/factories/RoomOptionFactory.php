<?php

namespace Database\Factories;

use App\Enums\RoomOptionUnitType;
use App\Enums\RoomPricePeriod;
use App\Enums\RoomPricingMode;
use App\Models\RoomOption;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomOption>
 */
class RoomOptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_type' => (new Trip)->getMorphClass(),
            'owner_id' => Trip::factory(),
            'unit_type' => RoomOptionUnitType::Room,
            'name' => ['en' => 'Double room', 'it' => 'Camera doppia'],
            'stars' => 4,
            'pricing_mode' => RoomPricingMode::PerRoom,
            'price_period' => RoomPricePeriod::PerNight,
            'price' => 60,
            'max_adults' => 2,
            'max_children' => 1,
            'max_total' => 3,
            'max_quantity' => 10,
            'is_active' => true,
            'sort' => 0,
        ];
    }

    public function perPerson(): static
    {
        return $this->state(['pricing_mode' => RoomPricingMode::PerPerson]);
    }

    public function perStay(): static
    {
        return $this->state(['price_period' => RoomPricePeriod::PerStay]);
    }
}
