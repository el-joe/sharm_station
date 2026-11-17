<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Review state of an AI suggestion: only pending suggestions can be edited, approved or rejected.
 */
enum SuggestionStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
