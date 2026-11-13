<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PackagePriceMode: string
{
    use HasOptions;

    case Fixed = 'fixed';
    case Computed = 'computed';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed price',
            self::Computed => 'Computed from trips',
        };
    }
}
