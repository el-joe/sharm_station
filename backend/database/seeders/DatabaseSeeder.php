<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with realistic demo content (EN + IT).
     */
    public function run(): void
    {
        $this->call([
            NationalitySeeder::class,
            CurrencySeeder::class,
            SettingSeeder::class,
            UserSeeder::class,
            PartnerSeeder::class,
            DestinationSeeder::class,
            TripSeeder::class,
            PackageSeeder::class,
            AccommodationSeeder::class,
            GoogleReviewSeeder::class,
            ContentSeeder::class,
            TransferSeeder::class,
            PaymentMethodSeeder::class,
            NationalityPriceSeeder::class,
            BookingSeeder::class,
            PlatformCompletionSeeder::class,
        ]);

        $this->command?->call('media:placeholders');
    }
}
