<?php

namespace App\DTOs\Pricing;

final readonly class UnitSelectionData
{
    public function __construct(
        public int $unitOptionId,
        public int $quantity,
    ) {}

    /**
     * @param  array{unit_option_id: int|string, quantity?: int|string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self((int) $data['unit_option_id'], (int) ($data['quantity'] ?? 1));
    }
}
