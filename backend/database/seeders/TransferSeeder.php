<?php

namespace Database\Seeders;

use App\Enums\HubType;
use App\Enums\VehicleType as VehicleCategory;
use App\Models\Destination;
use App\Models\TransferHub;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;

/**
 * Transfer hubs (airports), vehicles and a few fixed routes to popular hotel areas (SRS 5).
 */
class TransferSeeder extends Seeder
{
    public function run(): void
    {
        $sedan = VehicleType::query()->create(['name' => ['en' => 'Sedan', 'it' => 'Berlina'], 'category' => VehicleCategory::Sedan, 'capacity' => 3, 'luggage' => 3, 'multiplier' => 1, 'image' => '/images/placeholders/vehicle-1.jpg', 'sort' => 0]);
        $minivan = VehicleType::query()->create(['name' => ['en' => 'Minivan', 'it' => 'Minivan'], 'category' => VehicleCategory::Minivan, 'capacity' => 7, 'luggage' => 7, 'multiplier' => 1.4, 'image' => '/images/placeholders/vehicle-2.jpg', 'sort' => 1]);
        VehicleType::query()->create(['name' => ['en' => 'Minibus', 'it' => 'Minibus'], 'category' => VehicleCategory::Van, 'capacity' => 14, 'luggage' => 14, 'multiplier' => 1.9, 'min_fare' => 45, 'image' => '/images/placeholders/vehicle-3.jpg', 'sort' => 2]);

        $destinations = Destination::query()->pluck('id', 'slug');
        $tiers = [['up_to_km' => 20, 'rate' => 1.2], ['up_to_km' => 60, 'rate' => 0.9], ['up_to_km' => null, 'rate' => 0.7]];
        $extras = [
            ['code' => 'child_seat', 'label' => 'Child seat', 'price' => 5],
            ['code' => 'extra_stop', 'label' => 'Extra stop', 'price' => 10],
            ['code' => 'airport_sign', 'label' => 'Name sign at arrivals', 'price' => 0],
        ];

        $hubs = [
            ['Sharm El Sheikh Airport', 'Aeroporto di Sharm El Sheikh', 'ssh-airport', 'sharm-el-sheikh', 27.9773, 34.3950, 120, [
                ['Naama Bay', 27.9158, 34.3299, 20, 16], ['Sharks Bay', 27.9960, 34.4260, 18, 14], ['Old Market (Sharm El Maya)', 27.8640, 34.2950, 22, 18],
            ]],
            ['Hurghada Airport', 'Aeroporto di Hurghada', 'hrg-airport', 'hurghada', 27.1783, 33.7994, 120, [
                ['Hurghada Marina', 27.2290, 33.8410, 18, 14], ['El Gouna', 27.3940, 33.6780, 30, 24],
            ]],
            ['Luxor Airport', 'Aeroporto di Luxor', 'lxr-airport', 'luxor', 25.6710, 32.7066, 80, [
                ['Luxor Corniche hotels', 25.6990, 32.6390, 18, 14],
            ]],
            ['Cairo Airport', 'Aeroporto del Cairo', 'cai-airport', 'cairo', 30.1219, 31.4056, 80, [
                ['Giza pyramids hotels', 29.9870, 31.1350, 35, 28], ['Downtown Cairo', 30.0444, 31.2357, 25, 20],
            ]],
        ];

        foreach ($hubs as $sort => [$nameEn, $nameIt, $slug, $destination, $lat, $lng, $radius, $routes]) {
            $hub = TransferHub::query()->create([
                'name' => ['en' => $nameEn, 'it' => $nameIt],
                'slug' => $slug,
                'type' => HubType::Airport,
                'destination_id' => $destinations[$destination] ?? null,
                'lat' => $lat,
                'lng' => $lng,
                'radius_km' => $radius,
                'max_distance_km' => 250,
                'base_fare' => 10,
                'distance_tiers' => $tiers,
                'min_fare' => 25,
                'waiting_grace_minutes' => 60,
                'waiting_rate_per_hour' => 12,
                'night_surcharge' => 8,
                'fixed_fees' => 5,
                'extras' => $extras,
                'return_discount_percent' => 10,
                'deposit_percent' => null,
                'min_notice_hours' => 12,
                'vehicle_type_ids' => null,
                'is_enabled' => true,
                'sort' => $sort,
            ]);

            foreach ($routes as [$place, $routeLat, $routeLng, $price, $partnerPrice]) {
                $hub->fixedRoutes()->create([
                    'destination_name' => ['en' => $place, 'it' => $place],
                    'lat' => $routeLat,
                    'lng' => $routeLng,
                    'match_radius_km' => 2,
                    'price' => $price,
                    'partner_price' => $partnerPrice,
                ]);
            }
        }

        unset($sedan, $minivan);
    }
}
