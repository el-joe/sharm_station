<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum DiscountType: string
{
    use HasOptions;

    case Free = 'free';
    case Percent = 'percent';
    case FixedAmount = 'fixed_amount';
    case FixedPrice = 'fixed_price';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Percent => 'Percentage off adult price',
            self::FixedAmount => 'Fixed amount off',
            self::FixedPrice => 'Fixed child price',
        };
    }
}
