<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RoomType: string
{
    use HasOptions;

    case Single = 'single';
    case Double = 'double';
    case Twin = 'twin';
    case Triple = 'triple';
    case Family = 'family';
    case Suite = 'suite';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single',
            self::Double => 'Double',
            self::Twin => 'Twin',
            self::Triple => 'Triple',
            self::Family => 'Family',
            self::Suite => 'Suite',
        };
    }
}
