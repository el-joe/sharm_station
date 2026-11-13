<?php

namespace App\DTOs\Room;

use App\Enums\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final readonly class RoomFilterData
{
    public function __construct(
        public ?string $destination = null,
        public ?string $building = null,
        public ?RoomType $roomType = null,
        public ?int $guests = null,
        public ?float $priceMin = null,
        public ?float $priceMax = null,
        public ?int $stars = null,
        public string $sort = 'recommended',
        public int $perPage = 12,
        public ?CarbonImmutable $checkIn = null,
        public ?CarbonImmutable $checkOut = null,
        public bool $freeCancellation = false,
    ) {}

    /**
     * Number of nights of the requested stay, or null without dates.
     */
    public function nights(): ?int
    {
        return $this->checkIn !== null && $this->checkOut !== null ? (int) $this->checkIn->diffInDays($this->checkOut) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            destination: $data['destination'] ?? null,
            building: $data['building'] ?? null,
            roomType: isset($data['room_type']) ? RoomType::from($data['room_type']) : null,
            guests: isset($data['guests']) ? (int) $data['guests'] : null,
            priceMin: isset($data['price_min']) ? (float) $data['price_min'] : null,
            priceMax: isset($data['price_max']) ? (float) $data['price_max'] : null,
            stars: isset($data['stars']) ? (int) $data['stars'] : null,
            sort: $data['sort'] ?? 'recommended',
            perPage: (int) ($data['per_page'] ?? 12),
            checkIn: filled($data['check_in'] ?? null) ? CarbonImmutable::parse($data['check_in'])->startOfDay() : null,
            checkOut: filled($data['check_out'] ?? null) ? CarbonImmutable::parse($data['check_out'])->startOfDay() : null,
            freeCancellation: (bool) ($data['free_cancellation'] ?? false),
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    public function withPriceBounds(?float $priceMin, ?float $priceMax): self
    {
        return new self(
            destination: $this->destination,
            building: $this->building,
            roomType: $this->roomType,
            guests: $this->guests,
            priceMin: $priceMin,
            priceMax: $priceMax,
            stars: $this->stars,
            sort: $this->sort,
            perPage: $this->perPage,
            checkIn: $this->checkIn,
            checkOut: $this->checkOut,
            freeCancellation: $this->freeCancellation,
        );
    }
}
