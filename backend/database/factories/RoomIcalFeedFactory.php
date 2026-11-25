<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\RoomIcalFeed;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomIcalFeed>
 */
class RoomIcalFeedFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'name' => 'Booking.com',
            'url' => 'https://calendar.example.test/room-'.fake()->unique()->numberBetween(1, 99999).'.ics',
            'is_active' => true,
        ];
    }
}
