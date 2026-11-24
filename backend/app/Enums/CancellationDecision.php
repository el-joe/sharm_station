<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CancellationDecision: string
{
    use HasOptions;

    case Approved = 'approved';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Approved',
            self::Declined => 'Declined',
        };
    }
}
