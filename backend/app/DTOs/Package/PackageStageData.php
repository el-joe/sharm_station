<?php

namespace App\DTOs\Package;

/**
 * One stage of a package: destination, days and nights, accommodation text and the trips placed on its days.
 */
final readonly class PackageStageData
{
    /**
     * @param  array<string, string>  $accommodation
     * @param  list<array{day_number: int, trip_id: int}>  $trips  day_number is the day inside the stage
     */
    public function __construct(
        public int $destinationId,
        public int $days,
        public int $nights,
        public array $accommodation = [],
        public array $trips = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            destinationId: (int) $data['destination_id'],
            days: (int) $data['days'],
            nights: (int) ($data['nights'] ?? 0),
            accommodation: array_filter((array) ($data['accommodation'] ?? []), fn ($value): bool => filled($value)),
            trips: array_values(array_map(fn (array $trip): array => [
                'day_number' => (int) $trip['day_number'],
                'trip_id' => (int) $trip['trip_id'],
            ], array_filter($data['trips'] ?? [], fn ($trip): bool => is_array($trip) && filled($trip['trip_id'] ?? null)))),
        );
    }
}
