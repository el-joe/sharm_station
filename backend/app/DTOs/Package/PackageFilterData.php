<?php

namespace App\DTOs\Package;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Package listing filters. Price bounds are in the requested currency until the service converts them to EUR.
 */
final readonly class PackageFilterData
{
    public function __construct(
        public ?string $destination = null,
        public ?float $priceMin = null,
        public ?float $priceMax = null,
        public ?int $daysMin = null,
        public ?int $daysMax = null,
        public ?string $search = null,
        public string $sort = 'recommended',
        public int $perPage = 12,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            destination: $data['destination'] ?? null,
            priceMin: isset($data['price_min']) ? (float) $data['price_min'] : null,
            priceMax: isset($data['price_max']) ? (float) $data['price_max'] : null,
            daysMin: isset($data['days_min']) ? (int) $data['days_min'] : null,
            daysMax: isset($data['days_max']) ? (int) $data['days_max'] : null,
            search: $data['q'] ?? null,
            sort: $data['sort'] ?? 'recommended',
            perPage: (int) ($data['per_page'] ?? 12),
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    public function withPriceBounds(?float $priceMin, ?float $priceMax): self
    {
        return new self($this->destination, $priceMin, $priceMax, $this->daysMin, $this->daysMax, $this->search, $this->sort, $this->perPage);
    }
}
