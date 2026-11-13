<?php

namespace App\DTOs\Trip;

/**
 * One itinerary day with its ordered timeline items. Day numbers follow the submitted order.
 */
final readonly class TripDayData
{
    /**
     * @param  array<string, string|null>  $title
     * @param  array<string, string|null>  $description
     * @param  list<TimelineItemData>  $items
     */
    public function __construct(
        public int $dayNumber,
        public array $title,
        public array $description = [],
        public ?string $overnight = null,
        public array $items = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, int $dayNumber): self
    {
        $items = array_values($data['items'] ?? []);

        return new self(
            dayNumber: $dayNumber,
            title: array_filter((array) ($data['title'] ?? []), fn ($value): bool => filled($value)),
            description: array_filter((array) ($data['description'] ?? []), fn ($value): bool => filled($value)),
            overnight: filled($data['overnight'] ?? null) ? $data['overnight'] : null,
            items: array_map(fn (array $item, int $index): TimelineItemData => TimelineItemData::fromArray($item, $index), $items, array_keys($items)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'day_number' => $this->dayNumber,
            'title' => $this->title,
            'description' => $this->description === [] ? null : $this->description,
            'overnight' => $this->overnight,
        ];
    }
}
