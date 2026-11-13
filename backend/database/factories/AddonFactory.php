<?php

namespace Database\Factories;

use App\Enums\AddonPricingBasis;
use App\Models\Addon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Addon>
 */
class AddonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ['en' => 'Lunch upgrade', 'it' => 'Pranzo superiore'],
            'description' => ['en' => fake()->sentence(), 'it' => fake()->sentence()],
            'price' => 10,
            'partner_price' => null,
            'pricing_basis' => AddonPricingBasis::PerPerson,
            'required' => false,
            'max_quantity' => 10,
            'booking_types' => ['private', 'group'],
            'is_active' => true,
        ];
    }

    public function basis(AddonPricingBasis $basis, float $price): static
    {
        return $this->state(fn (array $attributes) => ['pricing_basis' => $basis, 'price' => $price]);
    }

    public function required(): static
    {
        return $this->state(fn (array $attributes) => ['required' => true]);
    }
}
