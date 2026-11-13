<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'rate_to_eur' => 1, 'margin_percent' => 0, 'sort' => 0],
            ['code' => 'USD', 'name' => 'US dollar', 'symbol' => '$', 'rate_to_eur' => 1.08, 'margin_percent' => 0, 'sort' => 1],
            ['code' => 'GBP', 'name' => 'British pound', 'symbol' => '£', 'rate_to_eur' => 0.85, 'margin_percent' => 0, 'sort' => 2],
            ['code' => 'EGP', 'name' => 'Egyptian pound', 'symbol' => 'E£', 'rate_to_eur' => 52.5, 'margin_percent' => 2, 'sort' => 3],
        ];

        foreach ($currencies as $currency) {
            Currency::query()->updateOrCreate(['code' => $currency['code']], [...$currency, 'is_enabled' => true]);
        }
    }
}
