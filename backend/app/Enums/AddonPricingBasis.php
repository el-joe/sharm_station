<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AddonPricingBasis: string
{
    use HasOptions;

    case PerPerson = 'per_person';
    case PerBooking = 'per_booking';
    case PerUnit = 'per_unit';

    public function label(): string
    {
        return match ($this) {
            self::PerPerson => 'Per person',
            self::PerBooking => 'Per booking',
            self::PerUnit => 'Per unit',
        };
    }
}
