<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PublishStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
        };
    }
}
