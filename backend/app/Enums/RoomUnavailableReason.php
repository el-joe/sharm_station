<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Why a room cannot be booked for the searched stay (rooms listing, SRS 6.3).
 */
enum RoomUnavailableReason: string
{
    use HasOptions;

    case Booked = 'booked';
    case Closed = 'closed';
    case MinStay = 'min_stay';
    case Capacity = 'capacity';

    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Already booked',
            self::Closed => 'Closed on these dates',
            self::MinStay => 'Minimum or maximum stay not met',
            self::Capacity => 'Too many guests for this room',
        };
    }
}
