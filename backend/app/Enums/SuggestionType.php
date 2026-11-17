<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Kind of AI suggestion created through the MCP server (CR-14).
 */
enum SuggestionType: string
{
    use HasOptions;

    case AccommodationCreate = 'accommodation_create';
    case SeoUpdate = 'seo_update';

    public function label(): string
    {
        return match ($this) {
            self::AccommodationCreate => 'Accommodation',
            self::SeoUpdate => 'SEO',
        };
    }
}
