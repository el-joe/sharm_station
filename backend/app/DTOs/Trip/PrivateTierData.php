<?php

namespace App\DTOs\Trip;

final readonly class PrivateTierData
{
    public function __construct(
        public int $minPeople,
        public ?int $maxPeople,
        public float $pricePerPerson,
        public ?int $id = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            minPeople: (int) $data['min_people'],
            maxPeople: isset($data['max_people']) && $data['max_people'] !== '' ? (int) $data['max_people'] : null,
            pricePerPerson: (float) $data['price_per_person'],
            id: isset($data['id']) ? (int) $data['id'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'min_people' => $this->minPeople,
            'max_people' => $this->maxPeople,
            'price_per_person' => $this->pricePerPerson,
        ];
    }
}
