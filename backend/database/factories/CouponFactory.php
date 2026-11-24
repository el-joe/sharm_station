<?php

namespace Database\Factories;

use App\Enums\CouponDiscountType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('CODE##??')),
            'description' => fake()->sentence(4),
            'discount_type' => CouponDiscountType::Percent,
            'value' => 10,
            'valid_from' => null,
            'valid_to' => null,
            'usage_limit' => null,
            'usage_limit_per_customer' => null,
            'minimum_total' => null,
            'services' => null,
            'partners_allowed' => false,
            'is_active' => true,
        ];
    }

    public function fixed(float $amount): static
    {
        return $this->state(fn (array $attributes) => ['discount_type' => CouponDiscountType::Fixed, 'value' => $amount]);
    }
}
