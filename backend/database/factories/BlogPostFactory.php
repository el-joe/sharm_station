<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'slug' => str($title)->slug()->toString(),
            'title' => ['en' => $title, 'it' => $title.' (IT)'],
            'excerpt' => ['en' => fake()->sentence(), 'it' => fake()->sentence()],
            'body' => ['en' => '<p>'.fake()->paragraph().'</p>', 'it' => '<p>'.fake()->paragraph().'</p>'],
            'author' => fake()->name(),
            'tags' => ['en' => ['Guide'], 'it' => ['Guida']],
            'status' => PublishStatus::Published,
            'published_at' => now()->subDay(),
            'reading_minutes' => 3,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PublishStatus::Draft, 'published_at' => null]);
    }
}
