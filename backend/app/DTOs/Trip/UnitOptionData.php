<?php

namespace App\DTOs\Trip;

final readonly class UnitOptionData
{
    /**
     * @param  array<string, string>  $name
     */
    public function __construct(
        public array $name,
        public int $seats,
        public float $price,
        public ?float $partnerPrice = null,
        public int $maxQuantity = 10,
        public bool $childrenAllowed = true,
        public int $sort = 0,
        public ?int $id = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: is_array($data['name']) ? $data['name'] : ['en' => (string) $data['name']],
            seats: (int) $data['seats'],
            price: (float) $data['price'],
            partnerPrice: isset($data['partner_price']) && $data['partner_price'] !== '' ? (float) $data['partner_price'] : null,
            maxQuantity: (int) ($data['max_quantity'] ?? 10),
            childrenAllowed: (bool) ($data['children_allowed'] ?? true),
            sort: (int) ($data['sort'] ?? 0),
            id: isset($data['id']) ? (int) $data['id'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'seats' => $this->seats,
            'price' => $this->price,
            'partner_price' => $this->partnerPrice,
            'max_quantity' => $this->maxQuantity,
            'children_allowed' => $this->childrenAllowed,
            'sort' => $this->sort,
        ];
    }
}
