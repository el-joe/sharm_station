<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Coordinates of the Sharm El Sheikh office (Naama Bay) for the contact map; only set when missing.
 */
class OfficeLocationSeeder extends Seeder
{
    public const LAT = 27.9158;

    public const LNG = 34.3300;

    public function run(): void
    {
        Setting::query()->firstOrCreate(['key' => 'site.lat'], ['value' => self::LAT]);
        Setting::query()->firstOrCreate(['key' => 'site.lng'], ['value' => self::LNG]);
        Setting::query()->firstOrCreate(['key' => 'site.address'], ['value' => 'Naama Bay, Sharm El Sheikh, Egypt']);
    }
}
