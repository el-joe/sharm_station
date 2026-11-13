<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CommissionCategory: string
{
    use HasOptions;

    case Trips = 'trips';
    case NileTrips = 'nile_trips';
    case Packages = 'packages';
    case Transfers = 'transfers';
    case Accommodation = 'accommodation';

    public function label(): string
    {
        return match ($this) {
            self::Trips => 'Trips',
            self::NileTrips => 'Nile trips',
            self::Packages => 'Packages',
            self::Transfers => 'Transfers',
            self::Accommodation => 'Accommodation',
        };
    }
}
