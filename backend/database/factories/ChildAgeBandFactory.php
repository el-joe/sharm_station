<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\ChildAgeBand;
use App\Models\ChildPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChildAgeBand>
 */
class ChildAgeBandFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'child_policy_id' => ChildPolicy::factory(),
            'min_age' => 4,
            'max_age' => 11,
            'discount_type' => DiscountType::Percent,
            'discount_value' => 50,
        ];
    }
}
