<?php

namespace App\DTOs\Transfer;

use Illuminate\Foundation\Http\FormRequest;

final readonly class FixedRouteData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes) {}

    public static function fromRequest(FormRequest $request): self
    {
        $data = $request->validated();

        return new self([
            'transfer_hub_id' => (int) $data['transfer_hub_id'],
            'vehicle_type_id' => filled($data['vehicle_type_id'] ?? null) ? (int) $data['vehicle_type_id'] : null,
            'destination_name' => array_filter((array) $data['destination_name'], fn ($value): bool => filled($value)),
            'lat' => (float) $data['lat'],
            'lng' => (float) $data['lng'],
            'match_radius_km' => (float) $data['match_radius_km'],
            'price' => (float) $data['price'],
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
        ]);
    }
}
