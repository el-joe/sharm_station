<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\TripUnitOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripUnitOption>
 */
class TripUnitOptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory()->perUnit(),
            'name' => ['en' => 'Single buggy', 'it' => 'Buggy singolo'],
            'seats' => 1,
            'price' => 35,
            'partner_price' => null,
            'max_quantity' => 10,
            'children_allowed' => true,
            'sort' => 0,
        ];
    }

    public function seats(int $seats, float $price, string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'seats' => $seats,
            'price' => $price,
            'name' => ['en' => $name, 'it' => $name],
        ]);
    }
}
