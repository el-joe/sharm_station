<?php

namespace Database\Factories;

use App\Models\NationalityGroup;
use App\Models\ProductPrice;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPrice>
 */
class ProductPriceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'priceable_type' => (new Trip)->getMorphClass(),
            'priceable_id' => Trip::factory(),
            'nationality_group_id' => NationalityGroup::factory(),
            'adult_price' => 40,
            'child_price' => 20,
        ];
    }
}
