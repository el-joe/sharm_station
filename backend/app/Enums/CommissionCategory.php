<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Partner commission types (CR-11). Accommodation has no commission: partners pay the customer price.
 */
enum CommissionCategory: string
{
    use HasOptions;

    case SingleDayTrips = 'single_day_trips';
    case MultiDayTrips = 'multi_day_trips';
    case NileTrips = 'nile_trips';
    case Packages = 'packages';
    case Transfers = 'transfers';

    public function label(): string
    {
        return match ($this) {
            self::SingleDayTrips => 'Single-day trips',
            self::MultiDayTrips => 'Multi-day trips',
            self::NileTrips => 'Nile trips',
            self::Packages => 'Packages',
            self::Transfers => 'Transfers',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SingleDayTrips => 'Single-day trips and their add-ons',
            self::MultiDayTrips => 'Multi-day trips, add-ons and optional rooms',
            self::NileTrips => 'Nile trips, add-ons and cabins',
            self::Packages => 'Packages and their add-ons',
            self::Transfers => 'Standalone transfers',
        };
    }
}
