<?php

namespace App\DTOs\Room;

use App\Enums\RoomOptionUnitType;
use App\Enums\RoomPricePeriod;
use App\Enums\RoomPricingMode;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin create/update payload of an optional trip or package room (SRS 7.1).
 */
final readonly class RoomOptionData
{
    /**
     * @param  array<string, string>  $name
     */
    public function __construct(
        public array $name,
        public RoomOptionUnitType $unitType,
        public ?int $stars,
        public RoomPricingMode $pricingMode,
        public RoomPricePeriod $pricePeriod,
        public float $price,
        public int $maxAdults,
        public int $maxChildren,
        public int $maxTotal,
        public int $maxQuantity,
        public bool $isActive,
        public int $sort = 0,
        public ?int $stagePosition = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: array_filter((array) $data['name'], fn ($value): bool => filled($value)),
            unitType: RoomOptionUnitType::from($data['unit_type']),
            stars: filled($data['stars'] ?? null) ? (int) $data['stars'] : null,
            pricingMode: RoomPricingMode::from($data['pricing_mode']),
            pricePeriod: RoomPricePeriod::from($data['price_period']),
            price: (float) $data['price'],
            maxAdults: (int) $data['max_adults'],
            maxChildren: (int) ($data['max_children'] ?? 0),
            maxTotal: (int) $data['max_total'],
            maxQuantity: (int) ($data['max_quantity'] ?? 10),
            isActive: (bool) ($data['is_active'] ?? false),
            sort: (int) ($data['sort'] ?? 0),
            stagePosition: filled($data['stage_position'] ?? null) ? (int) $data['stage_position'] : null,
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'unit_type' => $this->unitType,
            'stars' => $this->stars,
            'pricing_mode' => $this->pricingMode,
            'price_period' => $this->pricePeriod,
            'price' => $this->price,
            'max_adults' => $this->maxAdults,
            'max_children' => $this->maxChildren,
            'max_total' => $this->maxTotal,
            'max_quantity' => $this->maxQuantity,
            'is_active' => $this->isActive,
            'sort' => $this->sort,
            'stage_position' => $this->stagePosition,
        ];
    }
}
