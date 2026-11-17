<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Services of the partner New booking catalogue (SRS 9.3).
 */
enum CatalogueService: string
{
    use HasOptions;

    case SingleDayTrips = 'single_day_trips';
    case MultiDayTrips = 'multi_day_trips';
    case NileTrips = 'nile_trips';
    case Packages = 'packages';
    case Transfers = 'transfers';
    case Accommodation = 'accommodation';

    public function label(): string
    {
        return $this->commissionCategory()?->label() ?? 'Accommodation';
    }

    /**
     * Commission type of the service; accommodation has none (partners pay the customer price).
     */
    public function commissionCategory(): ?CommissionCategory
    {
        return CommissionCategory::tryFrom($this->value);
    }

    public function tripType(): ?TripType
    {
        return match ($this) {
            self::SingleDayTrips => TripType::SingleDay,
            self::MultiDayTrips => TripType::MultiDay,
            self::NileTrips => TripType::Nile,
            default => null,
        };
    }

    /**
     * Product type used by POST /partner/quotes and /partner/bookings.
     */
    public function productType(): string
    {
        return match ($this) {
            self::Packages => 'package',
            self::Transfers => 'transfer',
            self::Accommodation => 'room',
            default => 'trip',
        };
    }
}
