<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Who receives a notification: the booker (customer or partner), the back-office team, or a partner account.
 */
enum NotificationAudience: string
{
    use HasOptions;

    case Customer = 'customer';
    case Admin = 'admin';
    case Partner = 'partner';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer or partner',
            self::Admin => 'Admin team',
            self::Partner => 'Partner',
        };
    }
}
