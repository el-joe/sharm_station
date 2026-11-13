<?php

namespace Database\Factories;

use App\Enums\RoomAvailabilityStatus;
use App\Models\Room;
use App\Models\RoomAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomAvailability>
 */
class RoomAvailabilityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'date' => now()->addDays(10)->toDateString(),
            'status' => RoomAvailabilityStatus::Closed,
        ];
    }
}
