<?php

namespace App\DTOs\Addon;

use App\Enums\AddonPricingBasis;
use App\Enums\BookingType;
use Illuminate\Foundation\Http\FormRequest;

final readonly class AddonData
{
    /**
     * @param  array<string, string>  $name
     * @param  array<string, string>  $description
     * @param  list<BookingType>  $bookingTypes  empty = all booking types
     */
    public function __construct(
        public array $name,
        public float $price,
        public AddonPricingBasis $pricingBasis,
        public array $description = [],
        public bool $required = false,
        public int $maxQuantity = 1,
        public array $bookingTypes = [],
        public bool $isActive = true,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: array_filter((array) $data['name'], fn ($value): bool => filled($value)),
            price: (float) $data['price'],
            pricingBasis: AddonPricingBasis::from($data['pricing_basis']),
            description: array_filter((array) ($data['description'] ?? []), fn ($value): bool => filled($value)),
            required: (bool) ($data['required'] ?? false),
            maxQuantity: (int) ($data['max_quantity'] ?? 1),
            bookingTypes: array_values(array_map(BookingType::from(...), $data['booking_types'] ?? [])),
            isActive: (bool) ($data['is_active'] ?? false),
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
            'description' => $this->description === [] ? null : $this->description,
            'price' => $this->price,
            'pricing_basis' => $this->pricingBasis,
            'required' => $this->required,
            'max_quantity' => $this->maxQuantity,
            'booking_types' => $this->bookingTypes === [] ? null : array_map(fn (BookingType $type): string => $type->value, $this->bookingTypes),
            'is_active' => $this->isActive,
        ];
    }
}
