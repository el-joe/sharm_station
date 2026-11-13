<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ContactSubject: string
{
    use HasOptions;

    case Booking = 'booking';
    case Trip = 'trip';
    case Partner = 'partner';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Booking => 'A booking',
            self::Trip => 'A trip',
            self::Partner => 'Partnership',
            self::Other => 'Other',
        };
    }
}
