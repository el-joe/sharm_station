<?php

namespace App\DTOs\Trip;

final readonly class TimelineItemData
{
    /**
     * @param  array<string, string|null>  $title
     * @param  array<string, string|null>  $description
     */
    public function __construct(
        public array $title,
        public array $description = [],
        public ?string $timeFrom = null,
        public ?string $timeTo = null,
        public ?string $place = null,
        public int $sort = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, int $position = 0): self
    {
        return new self(
            title: array_filter((array) ($data['title'] ?? []), fn ($value): bool => filled($value)),
            description: array_filter((array) ($data['description'] ?? []), fn ($value): bool => filled($value)),
            timeFrom: filled($data['time_from'] ?? null) ? $data['time_from'] : null,
            timeTo: filled($data['time_to'] ?? null) ? $data['time_to'] : null,
            place: filled($data['place'] ?? null) ? $data['place'] : null,
            sort: $position,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description === [] ? null : $this->description,
            'time_from' => $this->timeFrom,
            'time_to' => $this->timeTo,
            'place' => $this->place,
            'sort' => $this->sort,
        ];
    }
}
