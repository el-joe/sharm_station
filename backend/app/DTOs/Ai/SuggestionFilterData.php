<?php

namespace App\DTOs\Ai;

use App\Enums\SuggestionStatus;
use App\Enums\SuggestionType;

/**
 * Admin inbox filters.
 */
final readonly class SuggestionFilterData
{
    public function __construct(
        public ?SuggestionType $type = null,
        public ?SuggestionStatus $status = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            SuggestionType::tryFrom((string) ($data['type'] ?? '')),
            SuggestionStatus::tryFrom((string) ($data['status'] ?? '')),
        );
    }
}
