<?php

namespace Database\Factories;

use App\Enums\VehicleType as VehicleCategory;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleType>
 */
class VehicleTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ['en' => 'Sedan', 'it' => 'Berlina'],
            'category' => VehicleCategory::Sedan,
            'capacity' => 3,
            'luggage' => 3,
            'multiplier' => 1,
            'min_fare' => null,
            'is_enabled' => true,
            'sort' => 0,
        ];
    }

    public function minivan(): static
    {
        return $this->state(fn (): array => [
            'name' => ['en' => 'Minivan', 'it' => 'Minivan'],
            'category' => VehicleCategory::Minivan,
            'capacity' => 7,
            'luggage' => 7,
            'multiplier' => 1.4,
            'sort' => 1,
        ]);
    }
}
