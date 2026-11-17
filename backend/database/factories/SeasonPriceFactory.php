<?php

namespace Database\Factories;

use App\Models\NationalityGroup;
use App\Models\SeasonPrice;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeasonPrice>
 */
class SeasonPriceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'priceable_type' => 'trip',
            'priceable_id' => Trip::factory(),
            'name' => ['en' => 'High season', 'it' => 'Alta stagione'],
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-12-19',
            'recurring_yearly' => false,
            'is_active' => true,
            'adult_price' => 30,
            'child_price' => null,
            'unit_prices' => null,
            'tier_prices' => null,
            'price_per_night' => null,
        ];
    }

    public function between(string $startsOn, string $endsOn, string $name = 'Season'): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'name' => ['en' => $name, 'it' => $name],
        ]);
    }

    public function recurring(): static
    {
        return $this->state(fn (array $attributes) => ['recurring_yearly' => true]);
    }

    public function forGroup(?NationalityGroup $group): static
    {
        return $this->state(fn (array $attributes) => ['nationality_group_id' => $group?->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
