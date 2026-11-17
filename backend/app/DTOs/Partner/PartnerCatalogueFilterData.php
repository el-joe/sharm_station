<?php

namespace App\DTOs\Partner;

use App\Enums\CatalogueService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters of GET /partner/catalogue and /partner/price-list.
 */
final readonly class PartnerCatalogueFilterData
{
    public function __construct(
        public ?CatalogueService $service = null,
        public ?string $nationalityCode = null,
        public ?string $destination = null,
        public ?string $search = null,
        public ?CarbonImmutable $date = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            service: isset($data['service']) ? CatalogueService::from($data['service']) : null,
            nationalityCode: isset($data['nationality']) ? strtoupper((string) $data['nationality']) : null,
            destination: $data['destination'] ?? null,
            search: filled($data['q'] ?? null) ? trim((string) $data['q']) : null,
            date: filled($data['date'] ?? null) ? CarbonImmutable::parse($data['date'])->startOfDay() : null,
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * @return list<string>
     */
    public function searchTokens(): array
    {
        return $this->search === null ? [] : array_values(array_filter(preg_split('/\s+/', mb_strtolower($this->search)) ?: []));
    }
}
