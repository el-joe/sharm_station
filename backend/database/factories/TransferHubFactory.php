<?php

namespace Database\Factories;

use App\Enums\HubType;
use App\Models\TransferHub;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Sharm El Sheikh airport: base 10, 1.20/km up to 20 km, 0.90/km up to 60 km, 0.70/km beyond, minimum 25.
 *
 * @extends Factory<TransferHub>
 */
class TransferHubFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Sharm El Sheikh Airport '.fake()->unique()->numberBetween(1, 99999);

        return [
            'name' => ['en' => $name, 'it' => $name],
            'slug' => Str::slug($name),
            'type' => HubType::Airport,
            'lat' => 27.9773,
            'lng' => 34.3950,
            'radius_km' => 120,
            'max_distance_km' => 150,
            'base_fare' => 10,
            'distance_tiers' => [
                ['up_to_km' => 20, 'rate' => 1.2],
                ['up_to_km' => 60, 'rate' => 0.9],
                ['up_to_km' => null, 'rate' => 0.7],
            ],
            'min_fare' => 25,
            'waiting_grace_minutes' => 30,
            'waiting_rate_per_hour' => 12,
            'night_surcharge' => 8,
            'fixed_fees' => 5,
            'extras' => [
                ['code' => 'child_seat', 'label' => 'Child seat', 'price' => 5],
                ['code' => 'extra_stop', 'label' => 'Extra stop', 'price' => 10],
                ['code' => 'airport_sign', 'label' => 'Name sign at arrivals', 'price' => 0],
            ],
            'return_discount_percent' => 10,
            'deposit_percent' => null,
            'min_notice_hours' => 12,
            'vehicle_type_ids' => null,
            'is_enabled' => true,
            'sort' => 0,
        ];
    }
}
