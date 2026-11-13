<?php

namespace Database\Seeders;

use App\DTOs\Booking\CreateBookingData;
use App\Enums\BookingType;
use App\Models\Room;
use App\Models\Trip;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Database\Seeder;

/**
 * Two demo bookings created through BookingService so the admin has data to show.
 */
class BookingSeeder extends Seeder
{
    public function run(BookingService $bookings): void
    {
        $customer = User::query()->where('email', 'customer@sharmstation.test')->firstOrFail();
        $buggy = Trip::query()->where('slug', 'desert-buggy-safari')->with('unitOptions')->firstOrFail();

        $bookings->createFromQuote(CreateBookingData::fromArray([
            'trip_slug' => $buggy->slug,
            'booking_type' => BookingType::Group->value,
            'travel_date' => now()->addDays(10)->toDateString(),
            'adults' => 3,
            'children' => 0,
            'units' => [
                ['unit_option_id' => $buggy->unitOptions[0]->id, 'quantity' => 1],
                ['unit_option_id' => $buggy->unitOptions[1]->id, 'quantity' => 1],
            ],
            'addons' => [],
            'lead' => ['name' => $customer->name, 'email' => $customer->email, 'phone' => '+39 333 000 0000'],
            'travelers' => [
                ['first_name' => 'Giulia', 'last_name' => 'Rossi', 'type' => 'adult'],
                ['first_name' => 'Paolo', 'last_name' => 'Rossi', 'type' => 'adult'],
                ['first_name' => 'Anna', 'last_name' => 'Rossi', 'type' => 'adult'],
            ],
        ], userId: $customer->id, locale: 'it'));

        $room = Room::query()->where('number', '201')->firstOrFail();

        $bookings->createFromQuote(CreateBookingData::fromArray([
            'room_id' => $room->id,
            'check_in' => now()->addDays(20)->toDateString(),
            'check_out' => now()->addDays(23)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'lead' => ['name' => 'John Smith', 'email' => 'john.smith@example.com'],
            'travelers' => [
                ['first_name' => 'John', 'last_name' => 'Smith', 'type' => 'adult'],
                ['first_name' => 'Kate', 'last_name' => 'Smith', 'type' => 'adult'],
            ],
        ]));
    }
}
