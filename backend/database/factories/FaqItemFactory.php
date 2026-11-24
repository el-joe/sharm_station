<?php

namespace Database\Factories;

use App\Models\FaqCategory;
use App\Models\FaqItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FaqItem>
 */
class FaqItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'faq_category_id' => FaqCategory::factory(),
            'question' => ['en' => fake()->sentence().'?', 'it' => fake()->sentence().'?'],
            'answer' => ['en' => '<p>'.fake()->sentence().'</p>', 'it' => '<p>'.fake()->sentence().'</p>'],
            'sort' => 0,
            'is_active' => true,
        ];
    }
}
