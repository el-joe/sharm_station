<?php

namespace App\DTOs\Package;

use App\Enums\PackagePriceMode;
use App\Enums\PublishStatus;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Package builder payload (admin). Translatable fields are {locale: value} maps.
 */
final readonly class PackageData
{
    /**
     * @param  array<string, mixed>  $attributes  scalar and JSON columns
     * @param  list<PackageStageData>  $stages
     * @param  list<IncludedTransferData>  $transfers
     * @param  list<int>  $addonIds
     */
    public function __construct(
        public array $attributes,
        public array $stages = [],
        public array $transfers = [],
        public array $addonIds = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $translated = fn (string $key): ?array => ($map = array_filter((array) ($data[$key] ?? []), fn ($value): bool => filled($value))) === [] ? null : $map;
        $lines = fn (string $key): ?array => ($map = array_filter(array_map(
            fn ($text): array => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $text) ?: []))),
            (array) ($data[$key] ?? []),
        ))) === [] ? null : $map;
        $nullableFloat = fn (string $key): ?float => filled($data[$key] ?? null) ? (float) $data[$key] : null;

        $stages = array_values(array_map(PackageStageData::fromArray(...), $data['stages'] ?? []));

        return new self(
            attributes: [
                'title' => $translated('title'),
                'slug' => $data['slug'],
                'summary' => $translated('summary'),
                'description' => $translated('description'),
                'cancellation_policy' => $translated('cancellation_policy'),
                'highlights' => $lines('highlights'),
                'included' => $lines('included'),
                'excluded' => $lines('excluded'),
                'destination_id' => filled($data['destination_id'] ?? null) ? (int) $data['destination_id'] : ($stages[0]->destinationId ?? null),
                'days' => (int) $data['days'],
                'nights' => (int) $data['nights'],
                'status' => PublishStatus::from($data['status']),
                'featured' => (bool) ($data['featured'] ?? false),
                'private_enabled' => (bool) ($data['private_enabled'] ?? false),
                'group_enabled' => (bool) ($data['group_enabled'] ?? false),
                'min_persons' => (int) ($data['min_persons'] ?? 1),
                'max_persons' => filled($data['max_persons'] ?? null) ? (int) $data['max_persons'] : null,
                'price_mode' => PackagePriceMode::from($data['price_mode']),
                'base_price' => $nullableFloat('base_price'),
                'base_child_price' => $nullableFloat('base_child_price'),
                'discount_percent' => $nullableFloat('discount_percent'),
                'deposit_percent' => (float) $data['deposit_percent'],
                'free_cancellation_hours' => filled($data['free_cancellation_hours'] ?? null) ? (int) $data['free_cancellation_hours'] : null,
                'cover_image' => $data['cover_image'] ?? null,
                'transfers_included' => true,
            ],
            stages: $stages,
            transfers: array_values(array_map(IncludedTransferData::fromArray(...), $data['transfers'] ?? [])),
            addonIds: array_values(array_map('intval', $data['addon_ids'] ?? [])),
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }
}
