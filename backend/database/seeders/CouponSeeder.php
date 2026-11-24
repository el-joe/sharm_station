<?php

namespace Database\Seeders;

use App\Enums\CouponDiscountType;
use App\Enums\ServiceType;
use App\Models\Coupon;
use Illuminate\Database\Seeder;

/**
 * Demo coupon codes; existing codes are left untouched.
 */
class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $coupons = [
            ['code' => 'WELCOME10', 'description' => 'Welcome discount for newsletter subscribers', 'discount_type' => CouponDiscountType::Percent, 'value' => 10,
                'usage_limit_per_customer' => 1, 'minimum_total' => 50, 'services' => null],
            ['code' => 'NILE50', 'description' => 'EUR 50 off Nile trips and packages', 'discount_type' => CouponDiscountType::Fixed, 'value' => 50,
                'usage_limit' => 100, 'usage_limit_per_customer' => 1, 'minimum_total' => 300, 'services' => [ServiceType::NileTrip->value, ServiceType::Package->value]],
            ['code' => 'AGENCY5', 'description' => 'Extra 5% for partner agencies with coupon permission', 'discount_type' => CouponDiscountType::Percent, 'value' => 5,
                'partners_allowed' => true, 'services' => null],
        ];

        foreach ($coupons as $coupon) {
            Coupon::query()->firstOrCreate(['code' => $coupon['code']], [
                'valid_from' => now()->startOfYear()->toDateString(),
                'valid_to' => now()->addYear()->endOfYear()->toDateString(),
                'is_active' => true,
                'partners_allowed' => false,
                ...$coupon,
            ]);
        }
    }
}
