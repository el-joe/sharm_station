<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'EUR',
            'name' => 'Euro',
            'symbol' => '€',
            'rate_to_eur' => 1,
            'margin_percent' => 0,
            'is_enabled' => true,
            'sort' => 0,
        ];
    }

    public function usd(): static
    {
        return $this->state(fn (array $attributes) => ['code' => 'USD', 'name' => 'US dollar', 'symbol' => '$', 'rate_to_eur' => 1.08, 'sort' => 1]);
    }
}
