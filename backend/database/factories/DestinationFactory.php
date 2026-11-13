<?php

namespace Database\Factories;

use App\Models\Destination;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Destination>
 */
class DestinationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'name' => ['en' => $name, 'it' => $name],
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => ['en' => fake()->sentence(), 'it' => fake()->sentence()],
            'image' => '/images/placeholders/destination-1.jpg',
            'sort' => 0,
            'is_active' => true,
        ];
    }
}
