<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CouponDiscountType: string
{
    use HasOptions;

    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Percentage',
            self::Fixed => 'Fixed amount (EUR)',
        };
    }
}
