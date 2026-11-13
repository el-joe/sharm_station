<?php

namespace App\DTOs\Pricing;

final readonly class AddonSelectionData
{
    public function __construct(
        public int $addonId,
        public ?int $quantity = null,
    ) {}

    /**
     * @param  array{addon_id: int|string, quantity?: int|string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self((int) $data['addon_id'], isset($data['quantity']) ? (int) $data['quantity'] : null);
    }
}
