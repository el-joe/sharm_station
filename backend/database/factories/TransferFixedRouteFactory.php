<?php

namespace Database\Factories;

use App\Models\TransferFixedRoute;
use App\Models\TransferHub;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransferFixedRoute>
 */
class TransferFixedRouteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transfer_hub_id' => TransferHub::factory(),
            'vehicle_type_id' => null,
            'destination_name' => ['en' => 'Naama Bay', 'it' => 'Naama Bay'],
            'lat' => 27.9158,
            'lng' => 34.3299,
            'match_radius_km' => 1.5,
            'price' => 20,
            'is_enabled' => true,
        ];
    }
}
