<?php

namespace App\DTOs\Search;

use App\Enums\SearchScope;
use Illuminate\Foundation\Http\FormRequest;

final readonly class SuggestQueryData
{
    /**
     * @param  list<string>  $tokens  Lower-case words of the query; every word must match.
     */
    public function __construct(
        public SearchScope $scope,
        public array $tokens = [],
        public ?int $hubId = null,
        public int $limit = 8,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $data = $request->validated();

        return new self(
            scope: SearchScope::from($data['scope']),
            tokens: self::tokenize((string) ($data['q'] ?? '')),
            hubId: isset($data['hub_id']) ? (int) $data['hub_id'] : null,
            limit: (int) ($data['limit'] ?? 8),
        );
    }

    /**
     * Split "Hurghada  sta" into ["hurghada", "sta"]; drop punctuation and keep at most 5 words.
     *
     * @return list<string>
     */
    public static function tokenize(string $query): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(trim($query))) ?: [];

        return array_slice(array_values(array_unique(array_filter($words, fn (string $word): bool => $word !== ''))), 0, 5);
    }
}
