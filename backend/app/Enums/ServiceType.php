<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ServiceType: string
{
    use HasOptions;

    case Trip = 'trip';
    case NileTrip = 'nile_trip';
    case Package = 'package';
    case Transfer = 'transfer';
    case Accommodation = 'accommodation';

    public function label(): string
    {
        return match ($this) {
            self::Trip => 'Trip',
            self::NileTrip => 'Nile trip',
            self::Package => 'Package',
            self::Transfer => 'Transfer',
            self::Accommodation => 'Accommodation',
        };
    }
}
