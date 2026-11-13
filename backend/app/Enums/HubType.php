<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum HubType: string
{
    use HasOptions;

    case Airport = 'airport';
    case Port = 'port';
    case City = 'city';

    public function label(): string
    {
        return match ($this) {
            self::Airport => 'Airport',
            self::Port => 'Port',
            self::City => 'City or zone',
        };
    }
}
