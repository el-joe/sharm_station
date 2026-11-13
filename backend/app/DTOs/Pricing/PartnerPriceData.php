<?php

namespace App\DTOs\Pricing;

use App\Enums\PriceRule;

final readonly class PartnerPriceData
{
    /**
     * @param  float|null  $commissionPercent  Set when the price comes from a commission rule.
     */
    public function __construct(
        public PriceRule $rule,
        public ?float $explicitUnitPrice = null,
        public ?float $commissionPercent = null,
    ) {}

    /**
     * @param  array{rule: PriceRule, explicit_unit_price?: float|null, commission_percent?: float|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['rule'], $data['explicit_unit_price'] ?? null, $data['commission_percent'] ?? null);
    }

    public function unitPrice(float $retailUnitPrice): float
    {
        if ($this->explicitUnitPrice !== null) {
            return round($this->explicitUnitPrice, 2);
        }

        return $this->applyCommission($retailUnitPrice);
    }

    /**
     * Method B multiplies the explicit unit price; method A reduces the full retail line.
     */
    public function lineTotal(float $retailLineTotal, int $quantity): float
    {
        if ($this->explicitUnitPrice !== null) {
            return round($this->explicitUnitPrice * $quantity, 2);
        }

        return $this->applyCommission($retailLineTotal);
    }

    private function applyCommission(float $amount): float
    {
        if ($this->commissionPercent === null) {
            return round($amount, 2);
        }

        return round($amount * (1 - $this->commissionPercent / 100), 2);
    }
}
