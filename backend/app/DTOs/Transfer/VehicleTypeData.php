<?php

namespace App\DTOs\Transfer;

use App\Enums\VehicleType;
use Illuminate\Foundation\Http\FormRequest;

final readonly class VehicleTypeData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes) {}

    public static function fromRequest(FormRequest $request): self
    {
        $data = $request->validated();

        return new self([
            'name' => array_filter((array) $data['name'], fn ($value): bool => filled($value)),
            'category' => VehicleType::from($data['category']),
            'capacity' => (int) $data['capacity'],
            'luggage' => (int) $data['luggage'],
            'multiplier' => (float) $data['multiplier'],
            'min_fare' => filled($data['min_fare'] ?? null) ? (float) $data['min_fare'] : null,
            'image' => $data['image'] ?? null,
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'sort' => (int) ($data['sort'] ?? 0),
        ]);
    }
}
