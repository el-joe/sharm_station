<?php

namespace Database\Factories;

use App\Models\GoogleReview;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GoogleReview>
 */
class GoogleReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'place_id' => 'ChIJ-test-place',
            'google_review_id' => 'places/ChIJ-test-place/reviews/'.Str::random(16),
            'author_name' => fake()->name(),
            'author_photo_url' => null,
            'rating' => fake()->numberBetween(4, 5),
            'text' => fake()->sentence(12),
            'language' => 'en',
            'published_at' => now()->subDays(fake()->numberBetween(1, 200)),
            'is_hidden' => false,
            'synced_at' => now(),
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['is_hidden' => true]);
    }
}
