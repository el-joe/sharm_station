<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * v2.3 content for an existing database: website pages, blog and FAQ, office location and demo coupons.
 * Safe to run more than once.
 */
class PlatformCompletionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([CmsContentSeeder::class, OfficeLocationSeeder::class, CouponSeeder::class, RoomOptionSeeder::class]);
    }
}
