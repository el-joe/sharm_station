<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo accounts. All demo users share the password defined below (development only).
 */
class UserSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        $users = [
            ['name' => 'Site Admin', 'email' => 'admin@sharmstation.test', 'role' => UserRole::SuperAdmin],
            ['name' => 'Mona Agent', 'email' => 'agent@sharmstation.test', 'role' => UserRole::Agent],
            ['name' => 'Luca Editor', 'email' => 'editor@sharmstation.test', 'role' => UserRole::Editor],
            ['name' => 'Giulia Rossi', 'email' => 'customer@sharmstation.test', 'role' => UserRole::Customer, 'locale' => 'it'],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(['email' => $user['email']], [
                'name' => $user['name'],
                'role' => $user['role'],
                'password' => self::DEMO_PASSWORD,
                'locale' => $user['locale'] ?? 'en',
                'currency' => 'EUR',
                'email_verified_at' => now(),
            ]);
        }
    }
}
