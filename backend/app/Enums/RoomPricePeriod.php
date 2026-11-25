<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Whether an optional room price is charged per night or once for the whole stay (SRS 7.1).
 */
enum RoomPricePeriod: string
{
    use HasOptions;

    case PerNight = 'per_night';
    case PerStay = 'per_stay';

    public function label(): string
    {
        return match ($this) {
            self::PerNight => 'Per night',
            self::PerStay => 'Per stay',
        };
    }
}
