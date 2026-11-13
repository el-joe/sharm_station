<?php

namespace Database\Factories;

use App\Enums\PricingMode;
use App\Enums\PublishStatus;
use App\Enums\TripType;
use App\Models\Destination;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    /**
     * Group per-person trip with a base adult price of EUR 25.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'type' => TripType::SingleDay,
            'destination_id' => Destination::factory(),
            'category' => 'marine',
            'title' => ['en' => Str::title($title), 'it' => Str::title($title)],
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'summary' => ['en' => fake()->sentence(), 'it' => fake()->sentence()],
            'description' => ['en' => fake()->paragraph(), 'it' => fake()->paragraph()],
            'duration_hours' => 8,
            'days' => 1,
            'nights' => 0,
            'private_enabled' => false,
            'group_enabled' => true,
            'pricing_mode' => PricingMode::PerPerson,
            'min_persons' => 1,
            'max_persons' => 12,
            'base_price' => 25,
            'base_child_price' => null,
            'partner_price' => null,
            'from_price' => 25,
            'deposit_percent' => 30,
            'free_cancellation_hours' => 24,
            'highlights' => ['en' => [fake()->sentence()], 'it' => [fake()->sentence()]],
            'included' => ['en' => ['Hotel pick-up'], 'it' => ['Prelievo in hotel']],
            'excluded' => ['en' => ['Tips'], 'it' => ['Mance']],
            'cover_image' => '/images/placeholders/trip-1.jpg',
            'status' => PublishStatus::Published,
            'featured' => false,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PublishStatus::Draft]);
    }

    public function perUnit(): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_mode' => PricingMode::PerUnit,
            'base_price' => null,
            'max_persons' => null,
        ]);
    }

    public function privateOnly(): static
    {
        return $this->state(fn (array $attributes) => ['private_enabled' => true, 'group_enabled' => false]);
    }

    public function privateAndGroup(): static
    {
        return $this->state(fn (array $attributes) => ['private_enabled' => true, 'group_enabled' => true]);
    }

    public function nile(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TripType::Nile,
            'days' => 5,
            'nights' => 4,
            'vessel_name' => 'M/S Nile Sun',
            'itinerary_from' => 'Luxor',
            'itinerary_to' => 'Aswan',
            'vessel_facilities' => ['pool', 'restaurant', 'sun_terrace', 'wifi'],
            'meal_type' => 'full_board',
        ]);
    }

    public function multiDay(int $days = 3): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TripType::MultiDay,
            'days' => $days,
            'nights' => $days - 1,
        ]);
    }
}
