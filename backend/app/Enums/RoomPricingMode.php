<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RoomPricingMode: string
{
    use HasOptions;

    case PerRoom = 'per_room';
    case PerPerson = 'per_person';

    public function label(): string
    {
        return match ($this) {
            self::PerRoom => 'Per room',
            self::PerPerson => 'Per person',
        };
    }
}
