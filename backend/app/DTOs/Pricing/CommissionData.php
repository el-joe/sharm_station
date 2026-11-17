<?php

namespace App\DTOs\Pricing;

use App\Enums\CommissionCategory;
use App\Enums\PriceRule;

/**
 * Commission percentage applied to a partner price and where it came from (CR-11).
 */
final readonly class CommissionData
{
    public function __construct(
        public float $percent,
        public PriceRule $source,
        public ?CommissionCategory $category = null,
    ) {}

    public static function none(?CommissionCategory $category = null): self
    {
        return new self(0.0, PriceRule::None, $category);
    }

    /**
     * Partner price of a customer amount, rounded to two decimals (applied per line).
     */
    public function apply(float $customerAmount): float
    {
        return round($customerAmount * (1 - $this->percent / 100), 2);
    }

    /**
     * @return array{category: string|null, percent: float, source: string}
     */
    public function toArray(): array
    {
        return [
            'category' => $this->category?->value,
            'percent' => $this->percent,
            'source' => $this->source->value,
        ];
    }
}
