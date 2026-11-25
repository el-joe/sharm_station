<?php

namespace App\DTOs\Pricing;

/**
 * One optional room chosen inside a trip or package (SRS 7.1) with the guests allocated to it.
 */
final readonly class RoomOptionSelectionData
{
    /**
     * @param  list<int>  $childAges
     */
    public function __construct(
        public int $roomOptionId,
        public int $adults,
        public array $childAges = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data  {room_option_id, adults, children_ages[]}
     */
    public static function fromArray(array $data): self
    {
        return new self(
            roomOptionId: (int) ($data['room_option_id'] ?? 0),
            adults: (int) ($data['adults'] ?? 0),
            childAges: array_values(array_map('intval', $data['children_ages'] ?? $data['child_ages'] ?? [])),
        );
    }

    public function guests(): int
    {
        return $this->adults + count($this->childAges);
    }
}
