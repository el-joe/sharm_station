<?php

namespace Database\Seeders;

use App\Enums\CommissionCategory;
use App\Enums\PartnerStatus;
use App\Enums\UserRole;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@sharmstation.test')->first();

        $user = User::query()->updateOrCreate(['email' => 'partner@sharmstation.test'], [
            'name' => 'Marco Bianchi',
            'role' => UserRole::Partner,
            'password' => UserSeeder::DEMO_PASSWORD,
            'locale' => 'it',
            'email_verified_at' => now(),
        ]);

        $partner = Partner::query()->updateOrCreate(['user_id' => $user->id], [
            'company' => 'Alpha Travel Srl',
            'tax_number' => 'IT01234567890',
            'country' => 'IT',
            'website' => 'https://alphatravel.example',
            'phone' => '+39 02 0000 0000',
            'status' => PartnerStatus::Active,
            'show_retail_price' => true,
            'approved_at' => now(),
            'approved_by' => $admin?->id,
        ]);

        // Overrides on top of the global settings (single-day 15, multi-day 10, Nile 10, packages 10, transfers 8).
        foreach ([
            CommissionCategory::MultiDayTrips->value => 12,
            CommissionCategory::NileTrips->value => 12,
        ] as $category => $percent) {
            $partner->categoryCommissions()->updateOrCreate(['category' => $category], ['commission_percent' => $percent]);
        }

        $pendingUser = User::query()->updateOrCreate(['email' => 'pending.partner@sharmstation.test'], [
            'name' => 'Sara Verdi',
            'role' => UserRole::Customer,
            'password' => UserSeeder::DEMO_PASSWORD,
            'email_verified_at' => now(),
        ]);

        Partner::query()->updateOrCreate(['user_id' => $pendingUser->id], [
            'company' => 'Beta Viaggi',
            'country' => 'IT',
            'status' => PartnerStatus::Pending,
            'message' => 'We send around 200 travellers a year to the Red Sea.',
        ]);
    }
}
