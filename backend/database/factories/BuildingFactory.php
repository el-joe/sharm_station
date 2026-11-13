<?php

namespace Database\Factories;

use App\Enums\BuildingType;
use App\Enums\PublishStatus;
use App\Models\Building;
use App\Models\Destination;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Building>
 */
class BuildingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Hotel '.fake()->unique()->lastName();

        return [
            'name' => ['en' => $name, 'it' => $name],
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'type' => BuildingType::Hotel,
            'destination_id' => Destination::factory(),
            'address' => fake()->streetAddress(),
            'stars' => 4,
            'description' => ['en' => fake()->paragraph(), 'it' => fake()->paragraph()],
            'amenities' => ['wifi', 'pool'],
            'cover_image' => '/images/placeholders/building-1.jpg',
            'check_in_from' => '14:00',
            'check_out_until' => '12:00',
            'deposit_percent' => 30,
            'taxes_included' => true,
            'tax_per_night' => null,
            'status' => PublishStatus::Published,
        ];
    }

    public function taxesExcluded(float $taxPerNight = 5): static
    {
        return $this->state(fn (array $attributes) => ['taxes_included' => false, 'tax_per_night' => $taxPerNight]);
    }
}
