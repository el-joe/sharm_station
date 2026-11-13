<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RoomAvailabilityStatus: string
{
    use HasOptions;

    case Open = 'open';
    case Closed = 'closed';
    case Held = 'held';
    case Booked = 'booked';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
            self::Held => 'Held',
            self::Booked => 'Booked',
        };
    }
}
