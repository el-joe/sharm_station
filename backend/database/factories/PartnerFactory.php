<?php

namespace Database\Factories;

use App\Enums\PartnerStatus;
use App\Enums\UserRole;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->role(UserRole::Partner),
            'company' => fake()->company(),
            'tax_number' => fake()->numerify('IT###########'),
            'country' => 'IT',
            'website' => fake()->url(),
            'phone' => fake()->e164PhoneNumber(),
            'status' => PartnerStatus::Active,
            'show_retail_price' => true,
            'coupons_allowed' => false,
            'approved_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PartnerStatus::Pending, 'approved_at' => null]);
    }
}
