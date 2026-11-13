<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Enums\PaymentStatus;
use App\Enums\PriceRule;
use App\Enums\PricingMode;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'BK-'.fake()->unique()->numberBetween(10000, 99999),
            'access_token' => Str::random(40),
            'service_type' => ServiceType::Trip,
            'booking_type' => BookingType::Group,
            'pricing_mode' => PricingMode::PerPerson,
            'bookable_type' => 'trip',
            'bookable_id' => Trip::factory(),
            'start_date' => now()->addDays(20)->toDateString(),
            'lead_name' => fake()->name(),
            'lead_email' => fake()->safeEmail(),
            'lead_phone' => fake()->e164PhoneNumber(),
            'adults' => 2,
            'children' => 0,
            'retail_total' => 50,
            'commission_amount' => 0,
            'price_rule' => PriceRule::Retail,
            'total' => 50,
            'deposit_percent' => 30,
            'deposit_amount' => 15,
            'balance_amount' => 35,
            'currency' => 'EUR',
            'exchange_rate' => 1,
            'total_in_currency' => 50,
            'status' => BookingStatus::AwaitingPayment,
            'payment_status' => PaymentStatus::AwaitingPayment,
            'locale' => 'en',
        ];
    }
}
