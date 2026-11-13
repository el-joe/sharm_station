<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum VehicleType: string
{
    use HasOptions;

    case Sedan = 'sedan';
    case Minivan = 'minivan';
    case Van = 'van';
    case Bus = 'bus';

    public function label(): string
    {
        return match ($this) {
            self::Sedan => 'Sedan',
            self::Minivan => 'Minivan',
            self::Van => 'Van',
            self::Bus => 'Bus',
        };
    }
}
