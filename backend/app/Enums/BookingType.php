<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum BookingType: string
{
    use HasOptions;

    case Private = 'private';
    case Group = 'group';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Private',
            self::Group => 'Group',
        };
    }
}
