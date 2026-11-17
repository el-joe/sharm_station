<?php

namespace App\DTOs\Room;

use App\Enums\RoomPricingMode;
use App\Enums\RoomType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin create/update payload for a room.
 */
final readonly class RoomData
{
    /**
     * @param  array<string, string>  $name
     * @param  array<string, mixed>  $attributes  Remaining columns (beds, view, description, amenities, number...).
     */
    public function __construct(
        public int $buildingId,
        public array $name,
        public RoomType $roomType,
        public int $maxAdults,
        public int $maxChildren,
        public int $maxTotal,
        public RoomPricingMode $pricingMode,
        public float $basePrice,
        public int $minNights = 1,
        public ?int $maxNights = null,
        public array $attributes = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $known = ['building_id', 'name', 'room_type', 'max_adults', 'max_children', 'max_total', 'pricing_mode', 'base_price', 'min_nights', 'max_nights'];

        return new self(
            buildingId: (int) $data['building_id'],
            name: is_array($data['name']) ? $data['name'] : ['en' => (string) $data['name']],
            roomType: RoomType::from($data['room_type']),
            maxAdults: (int) $data['max_adults'],
            maxChildren: (int) ($data['max_children'] ?? 0),
            maxTotal: (int) $data['max_total'],
            pricingMode: RoomPricingMode::from($data['pricing_mode'] ?? RoomPricingMode::PerRoom->value),
            basePrice: (float) $data['base_price'],
            minNights: (int) ($data['min_nights'] ?? 1),
            maxNights: isset($data['max_nights']) && $data['max_nights'] !== '' ? (int) $data['max_nights'] : null,
            attributes: array_diff_key($data, array_flip($known)),
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
            ...$this->attributes,
            'building_id' => $this->buildingId,
            'name' => $this->name,
            'room_type' => $this->roomType,
            'max_adults' => $this->maxAdults,
            'max_children' => $this->maxChildren,
            'max_total' => $this->maxTotal,
            'pricing_mode' => $this->pricingMode,
            'base_price' => $this->basePrice,
            'min_nights' => $this->minNights,
            'max_nights' => $this->maxNights,
        ];
    }
}
