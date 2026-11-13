<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TransferDirection: string
{
    use HasOptions;

    case FromHub = 'from_hub';
    case ToHub = 'to_hub';

    public function label(): string
    {
        return match ($this) {
            self::FromHub => 'From the hub',
            self::ToHub => 'To the hub',
        };
    }
}
