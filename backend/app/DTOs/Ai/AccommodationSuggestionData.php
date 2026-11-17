<?php

namespace App\DTOs\Ai;

use Illuminate\Support\Str;

/**
 * A proposed building with its rooms (MCP tool "create_accommodation_suggestion" or the admin review form).
 *
 * The normalized payload uses the admin form field names: building {name {en,it}, slug, type, destination_slug,
 * address, lat, lng, stars, description {en,it}, amenities, check_in_from, check_out_until, taxes_included,
 * tax_per_night, phone, email} and rooms [{name {en,it}, room_type, max_adults, max_children, max_total,
 * beds {en,it}, size_m2, base_price, min_nights, max_nights, amenities, description {en,it}}].
 */
final readonly class AccommodationSuggestionData
{
    /**
     * @param  array<string, mixed>  $building
     * @param  list<array<string, mixed>>  $rooms
     */
    public function __construct(
        public array $building,
        public array $rooms,
        public ?string $notes = null,
    ) {}

    /**
     * Maps the flat tool arguments (name_en, description_it, rooms[].base_price_per_night...) to the payload.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public static function payloadFromToolArguments(array $arguments): array
    {
        $text = fn (mixed $value): ?string => is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
        $locales = fn (array $source, string $prefix): array => array_filter(['en' => $text($source["{$prefix}_en"] ?? null), 'it' => $text($source["{$prefix}_it"] ?? null)], fn (?string $value): bool => $value !== null);
        $list = fn (mixed $value): array => is_array($value) ? array_values(array_filter(array_map($text, $value))) : [];

        $taxesIncluded = $arguments['taxes_included'] ?? true;

        $building = [
            'name' => $locales($arguments, 'name'),
            'slug' => Str::slug((string) ($text($arguments['slug'] ?? null) ?? $text($arguments['name_en'] ?? null) ?? '')),
            'type' => $text($arguments['type'] ?? null) ?? 'hotel',
            'destination_slug' => $text($arguments['destination'] ?? null),
            'address' => $text($arguments['address'] ?? null),
            'lat' => $arguments['lat'] ?? null,
            'lng' => $arguments['lng'] ?? null,
            'stars' => $arguments['stars'] ?? null,
            'description' => $locales($arguments, 'description') ?: null,
            'amenities' => $list($arguments['amenities'] ?? []),
            'check_in_from' => $text($arguments['check_in_from'] ?? null),
            'check_out_until' => $text($arguments['check_out_until'] ?? null),
            'taxes_included' => is_bool($taxesIncluded) ? $taxesIncluded : filter_var($taxesIncluded, FILTER_VALIDATE_BOOLEAN),
            'tax_per_night' => $arguments['tax_per_night'] ?? null,
            'phone' => $text($arguments['phone'] ?? null),
            'email' => $text($arguments['email'] ?? null),
        ];

        $rooms = [];

        foreach (is_array($arguments['rooms'] ?? null) ? array_values($arguments['rooms']) : [] as $room) {
            $room = is_array($room) ? $room : [];
            $rooms[] = [
                'name' => $locales($room, 'name'),
                'room_type' => $text($room['room_type'] ?? null),
                'max_adults' => $room['max_adults'] ?? null,
                'max_children' => $room['max_children'] ?? 0,
                'max_total' => $room['max_total'] ?? null,
                'beds' => $locales($room, 'beds') ?: null,
                'size_m2' => $room['size_m2'] ?? null,
                'base_price' => $room['base_price_per_night'] ?? null,
                'min_nights' => $room['min_nights'] ?? 1,
                'max_nights' => $room['max_nights'] ?? null,
                'amenities' => $list($room['amenities'] ?? []),
                'description' => $locales($room, 'description') ?: null,
            ];
        }

        return ['building' => $building, 'rooms' => $rooms, 'notes' => $text($arguments['notes'] ?? null)];
    }

    /**
     * @param  array<string, mixed>  $validated  Normalized payload that passed AccommodationSuggestionRequest::payloadRules().
     */
    public static function fromArray(array $validated): self
    {
        $building = $validated['building'];
        $building['taxes_included'] = (bool) ($building['taxes_included'] ?? true);

        if ($building['taxes_included']) {
            $building['tax_per_night'] = null;
        }

        $rooms = array_values(array_map(function (array $room): array {
            foreach (['beds', 'description'] as $field) {
                $room[$field] = array_filter((array) ($room[$field] ?? []), fn ($value): bool => filled($value)) ?: null;
            }

            $room['amenities'] = array_values($room['amenities'] ?? []);

            return $room;
        }, $validated['rooms'] ?? []));

        return new self($building, $rooms, $validated['notes'] ?? null);
    }

    /**
     * @return array{building: array<string, mixed>, rooms: list<array<string, mixed>>, notes: string|null}
     */
    public function toArray(): array
    {
        return ['building' => $this->building, 'rooms' => $this->rooms, 'notes' => $this->notes];
    }
}
