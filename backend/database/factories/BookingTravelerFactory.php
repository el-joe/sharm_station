<?php

namespace Database\Factories;

use App\Enums\TravelerType;
use App\Models\Booking;
use App\Models\BookingTraveler;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingTraveler>
 */
class BookingTravelerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'type' => TravelerType::Adult,
            'age' => null,
        ];
    }
}
