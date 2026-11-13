<?php

namespace App\DTOs\Trip;

use App\Enums\BookingType;
use App\Enums\PricingMode;
use App\Enums\TripType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Listing filters. There is intentionally no date filter (CR-5).
 * Price bounds are expressed in $currency and converted to EUR by the service.
 */
final readonly class TripFilterData
{
    /**
     * @param  list<TripType>  $types
     */
    public function __construct(
        public array $types = [],
        public ?string $destination = null,
        public ?BookingType $bookingType = null,
        public ?PricingMode $pricingMode = null,
        public ?float $priceMin = null,
        public ?float $priceMax = null,
        public bool $freeCancellation = false,
        public ?string $category = null,
        public ?string $search = null,
        public string $sort = 'recommended',
        public int $perPage = 12,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $types = $data['type'] ?? [];
        $types = is_array($types) ? $types : explode(',', (string) $types);

        return new self(
            types: array_values(array_map(fn (string $type): TripType => TripType::from($type), array_filter($types))),
            destination: $data['destination'] ?? null,
            bookingType: isset($data['booking_type']) ? BookingType::from($data['booking_type']) : null,
            pricingMode: isset($data['pricing_mode']) ? PricingMode::from($data['pricing_mode']) : null,
            priceMin: isset($data['price_min']) ? (float) $data['price_min'] : null,
            priceMax: isset($data['price_max']) ? (float) $data['price_max'] : null,
            freeCancellation: (bool) ($data['free_cancellation'] ?? false),
            category: $data['category'] ?? null,
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
        return new self(
            types: $this->types,
            destination: $this->destination,
            bookingType: $this->bookingType,
            pricingMode: $this->pricingMode,
            priceMin: $priceMin,
            priceMax: $priceMax,
            freeCancellation: $this->freeCancellation,
            category: $this->category,
            search: $this->search,
            sort: $this->sort,
            perPage: $this->perPage,
        );
    }
}
