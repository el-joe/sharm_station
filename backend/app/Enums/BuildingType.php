<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum BuildingType: string
{
    use HasOptions;

    case Hotel = 'hotel';
    case Apartment = 'apartment';
    case Villa = 'villa';
    case Hostel = 'hostel';

    public function label(): string
    {
        return match ($this) {
            self::Hotel => 'Hotel',
            self::Apartment => 'Apartment building',
            self::Villa => 'Villa',
            self::Hostel => 'Hostel',
        };
    }
}
