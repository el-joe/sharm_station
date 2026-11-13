<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TravelerType: string
{
    use HasOptions;

    case Adult = 'adult';
    case Child = 'child';

    public function label(): string
    {
        return match ($this) {
            self::Adult => 'Adult',
            self::Child => 'Child',
        };
    }
}
