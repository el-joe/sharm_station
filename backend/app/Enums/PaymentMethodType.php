<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Online gateway (paid on the site) or manual method with proof upload (SRS 10.2, 10.3).
 */
enum PaymentMethodType: string
{
    use HasOptions;

    case Online = 'online';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online gateway',
            self::Manual => 'Manual transfer',
        };
    }
}
