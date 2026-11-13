<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Currency: string
{
    use HasOptions;

    case Eur = 'EUR';
    case Usd = 'USD';
    case Gbp = 'GBP';
    case Egp = 'EGP';

    public function label(): string
    {
        return match ($this) {
            self::Eur => 'Euro',
            self::Usd => 'US dollar',
            self::Gbp => 'British pound',
            self::Egp => 'Egyptian pound',
        };
    }
}
