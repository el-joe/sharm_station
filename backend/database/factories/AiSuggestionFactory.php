<?php

namespace Database\Factories;

use App\Enums\SuggestionStatus;
use App\Enums\SuggestionType;
use App\Models\AiSuggestion;
use App\Models\Destination;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiSuggestion>
 */
class AiSuggestionFactory extends Factory
{
    /**
     * Pending accommodation suggestion (one hotel with one double room) for an existing destination.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => SuggestionType::AccommodationCreate,
            'payload' => fn (): array => [
                'building' => [
                    'name' => ['en' => 'Coral Bay Resort', 'it' => 'Coral Bay Resort'],
                    'slug' => 'coral-bay-resort',
                    'type' => 'hotel',
                    'destination_slug' => Destination::factory()->create()->slug,
                    'address' => 'Village Road, Hurghada',
                    'lat' => 27.2,
                    'lng' => 33.8,
                    'stars' => 4,
                    'description' => ['en' => 'Beachfront hotel.'],
                    'amenities' => ['wifi', 'pool'],
                    'check_in_from' => '14:00',
                    'check_out_until' => '12:00',
                    'taxes_included' => true,
                    'tax_per_night' => null,
                    'phone' => null,
                    'email' => null,
                ],
                'rooms' => [[
                    'name' => ['en' => 'Double sea view'],
                    'room_type' => 'double',
                    'max_adults' => 2,
                    'max_children' => 1,
                    'max_total' => 3,
                    'beds' => ['en' => '1 king bed'],
                    'size_m2' => 30,
                    'base_price' => 95,
                    'min_nights' => 1,
                    'max_nights' => null,
                    'amenities' => ['wifi'],
                    'description' => null,
                ]],
                'notes' => null,
            ],
            'original' => null,
            'status' => SuggestionStatus::Pending,
            'source' => 'mcp',
        ];
    }
}
