<?php

namespace Database\Factories;

use App\Enums\RoomPricingMode;
use App\Enums\RoomStatus;
use App\Enums\RoomType;
use App\Models\Building;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'name' => ['en' => 'Deluxe double room', 'it' => 'Camera doppia deluxe'],
            'number' => (string) fake()->numberBetween(100, 499),
            'room_type' => RoomType::Double,
            'max_adults' => 2,
            'max_children' => 1,
            'max_total' => 3,
            'beds' => ['en' => '1 king bed', 'it' => '1 letto king'],
            'size_m2' => 28,
            'amenities' => ['wifi', 'air_conditioning'],
            'cover_image' => '/images/placeholders/room-1.jpg',
            'pricing_mode' => RoomPricingMode::PerRoom,
            'base_price' => 80,
            'from_price' => 80,
            'min_nights' => 1,
            'max_nights' => 30,
            'status' => RoomStatus::Active,
        ];
    }
}
