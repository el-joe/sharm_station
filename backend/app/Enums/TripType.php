<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TripType: string
{
    use HasOptions;

    case SingleDay = 'single_day';
    case MultiDay = 'multi_day';
    case Nile = 'nile';

    public function label(): string
    {
        return match ($this) {
            self::SingleDay => 'Single-day trip',
            self::MultiDay => 'Multi-day trip',
            self::Nile => 'Nile trip',
        };
    }

    public function commissionCategory(): CommissionCategory
    {
        return $this === self::Nile ? CommissionCategory::NileTrips : CommissionCategory::Trips;
    }

    public function serviceType(): ServiceType
    {
        return $this === self::Nile ? ServiceType::NileTrip : ServiceType::Trip;
    }
}
