<?php

namespace App\DTOs\Pricing;

/**
 * One room of an accommodation request with the guests allocated to it (SRS 7.2) and its extra beds and cots.
 */
final readonly class RoomAllocationData
{
    /**
     * @param  list<int>  $childAges
     */
    public function __construct(
        public int $roomId,
        public int $adults,
        public array $childAges = [],
        public int $extraBeds = 0,
        public int $cots = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $data  {room_id, adults, children_ages[] (or child_ages[]), extra_beds?, cots?}
     */
    public static function fromArray(array $data): self
    {
        return new self(
            roomId: (int) ($data['room_id'] ?? 0),
            adults: (int) ($data['adults'] ?? 0),
            childAges: array_values(array_map('intval', $data['children_ages'] ?? $data['child_ages'] ?? [])),
            extraBeds: (int) ($data['extra_beds'] ?? 0),
            cots: (int) ($data['cots'] ?? 0),
        );
    }

    public function children(): int
    {
        return count($this->childAges);
    }

    public function guests(): int
    {
        return $this->adults + $this->children();
    }

    /**
     * @return array{room_id: int, adults: int, children_ages: list<int>, extra_beds: int, cots: int}
     */
    public function toArray(): array
    {
        return [
            'room_id' => $this->roomId,
            'adults' => $this->adults,
            'children_ages' => $this->childAges,
            'extra_beds' => $this->extraBeds,
            'cots' => $this->cots,
        ];
    }
}
