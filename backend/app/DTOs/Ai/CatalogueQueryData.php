<?php

namespace App\DTOs\Ai;

use App\Enums\TripType;
use Illuminate\Support\Str;

/**
 * Filters of the MCP read tools (list_trips, list_packages, list_accommodations).
 */
final readonly class CatalogueQueryData
{
    public const PER_PAGE = 20;

    /**
     * @param  list<string>  $tokens  Lower-cased search words.
     */
    public function __construct(
        public ?TripType $type = null,
        public ?string $destination = null,
        public array $tokens = [],
        public int $page = 1,
        public string $locale = 'en',
        public int $perPage = self::PER_PAGE,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments  Validated tool arguments.
     */
    public static function fromArray(array $arguments): self
    {
        $query = Str::lower(trim((string) ($arguments['q'] ?? '')));

        return new self(
            type: isset($arguments['type']) ? TripType::from($arguments['type']) : null,
            destination: filled($arguments['destination'] ?? null) ? (string) $arguments['destination'] : null,
            tokens: $query === '' ? [] : array_values(array_filter(preg_split('/\s+/', $query) ?: [])),
            page: max(1, (int) ($arguments['page'] ?? 1)),
            locale: (string) ($arguments['locale'] ?? 'en'),
        );
    }
}
