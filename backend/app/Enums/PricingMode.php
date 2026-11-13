<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PricingMode: string
{
    use HasOptions;

    case PerPerson = 'per_person';
    case PerUnit = 'per_unit';

    public function label(): string
    {
        return match ($this) {
            self::PerPerson => 'Per person',
            self::PerUnit => 'Per unit',
        };
    }
}
