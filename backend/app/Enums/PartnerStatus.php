<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PartnerStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Rejected => 'Rejected',
        };
    }
}
