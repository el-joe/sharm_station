<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Unit type of an optional room inside a multi-day trip, Nile trip or package (SRS 7.1).
 */
enum RoomOptionUnitType: string
{
    use HasOptions;

    case Room = 'room';
    case Suite = 'suite';
    case Cabin = 'cabin';
    case Villa = 'villa';
    case Tent = 'tent';

    public function label(): string
    {
        return match ($this) {
            self::Room => 'Room',
            self::Suite => 'Suite',
            self::Cabin => 'Cabin',
            self::Villa => 'Villa',
            self::Tent => 'Tent',
        };
    }
}
