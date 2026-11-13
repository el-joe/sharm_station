<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public const BUSINESS_PLACE_ID = 'ChIJsharmstation-demo';

    public function run(): void
    {
        $settings = [
            'site.name' => 'Sharm Station Viaggi',
            'site.email' => 'info@sharmstation.test',
            'site.phone' => '+20 69 360 0000',
            'site.whatsapp' => '+20 100 000 0000',
            'site.address' => 'Naama Bay, Sharm El Sheikh, Egypt',
            'site.social' => ['facebook' => 'https://facebook.com/sharmstation', 'instagram' => 'https://instagram.com/sharmstation'],
            'site.travellers_served' => 12500,
            'booking.default_deposit_percent' => 30,
            'accommodation.hold_minutes' => 30,
            'google_reviews.place_id' => self::BUSINESS_PLACE_ID,
            'payment.methods' => [
                ['code' => 'stripe', 'type' => 'online', 'name' => 'Credit or debit card', 'currencies' => ['EUR', 'USD', 'GBP']],
                ['code' => 'paypal', 'type' => 'online', 'name' => 'PayPal', 'currencies' => ['EUR']],
                ['code' => 'paymob', 'type' => 'online', 'name' => 'Paymob (EGP cards)', 'currencies' => ['EGP']],
                ['code' => 'bank_transfer', 'type' => 'manual', 'name' => 'Bank transfer', 'proof_required' => true, 'currencies' => []],
                ['code' => 'instapay', 'type' => 'manual', 'name' => 'InstaPay', 'proof_required' => true, 'currencies' => ['EGP']],
            ],
        ];

        foreach ($settings as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
