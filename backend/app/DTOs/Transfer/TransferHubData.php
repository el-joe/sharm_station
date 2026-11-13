<?php

namespace App\DTOs\Transfer;

use App\Enums\HubType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin payload of a transfer hub and its pricing settings.
 */
final readonly class TransferHubData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $float = fn (string $key, ?float $default = 0.0): ?float => filled($data[$key] ?? null) ? (float) $data[$key] : $default;

        return new self([
            'name' => array_filter((array) $data['name'], fn ($value): bool => filled($value)),
            'slug' => $data['slug'],
            'type' => HubType::from($data['type']),
            'destination_id' => filled($data['destination_id'] ?? null) ? (int) $data['destination_id'] : null,
            'lat' => (float) $data['lat'],
            'lng' => (float) $data['lng'],
            'radius_km' => (float) $data['radius_km'],
            'max_distance_km' => $float('max_distance_km', null),
            'base_fare' => $float('base_fare'),
            'min_fare' => $float('min_fare'),
            'distance_tiers' => array_values(array_map(fn (array $tier): array => [
                'up_to_km' => filled($tier['up_to_km'] ?? null) ? (float) $tier['up_to_km'] : null,
                'rate' => (float) $tier['rate'],
            ], array_filter($data['distance_tiers'] ?? [], fn ($tier): bool => is_array($tier) && filled($tier['rate'] ?? null)))),
            'waiting_grace_minutes' => (int) ($data['waiting_grace_minutes'] ?? 0),
            'waiting_rate_per_hour' => $float('waiting_rate_per_hour'),
            'night_surcharge' => $float('night_surcharge'),
            'fixed_fees' => $float('fixed_fees'),
            'extras' => array_values(array_map(fn (array $extra): array => [
                'code' => (string) $extra['code'],
                'label' => (string) $extra['label'],
                'price' => (float) ($extra['price'] ?? 0),
            ], array_filter($data['extras'] ?? [], fn ($extra): bool => is_array($extra) && filled($extra['code'] ?? null)))),
            'return_discount_percent' => $float('return_discount_percent'),
            'deposit_percent' => $float('deposit_percent', null),
            'min_notice_hours' => (int) ($data['min_notice_hours'] ?? 12),
            'vehicle_type_ids' => array_values(array_map('intval', $data['vehicle_type_ids'] ?? [])) ?: null,
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'sort' => (int) ($data['sort'] ?? 0),
        ]);
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }
}
