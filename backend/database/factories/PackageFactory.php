<?php

namespace Database\Factories;

use App\Enums\PackagePriceMode;
use App\Enums\PricingMode;
use App\Enums\PublishStatus;
use App\Models\Destination;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'destination_id' => Destination::factory(),
            'title' => ['en' => Str::title($title), 'it' => Str::title($title)],
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'summary' => ['en' => fake()->sentence(), 'it' => fake()->sentence()],
            'description' => ['en' => fake()->paragraph(), 'it' => fake()->paragraph()],
            'days' => 8,
            'nights' => 7,
            'private_enabled' => true,
            'group_enabled' => true,
            'pricing_mode' => PricingMode::PerPerson,
            'min_persons' => 1,
            'max_persons' => 10,
            'price_mode' => PackagePriceMode::Fixed,
            'base_price' => 990,
            'base_child_price' => 690,
            'from_price' => 990,
            'deposit_percent' => 30,
            'cover_image' => '/images/placeholders/package-1.jpg',
            'status' => PublishStatus::Published,
            'featured' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => PublishStatus::Draft]);
    }
}
