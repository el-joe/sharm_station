<?php

namespace App\DTOs\Currency;

/**
 * One row of the admin currencies table.
 */
final readonly class CurrencyRateData
{
    public function __construct(
        public int $id,
        public float $rateToEur,
        public float $marginPercent,
        public bool $isEnabled,
        public bool $isManualRate,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(int $id, array $data): self
    {
        return new self(
            id: $id,
            rateToEur: (float) $data['rate_to_eur'],
            marginPercent: (float) ($data['margin_percent'] ?? 0),
            isEnabled: (bool) ($data['is_enabled'] ?? false),
            isManualRate: (bool) ($data['is_manual_rate'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'rate_to_eur' => $this->rateToEur,
            'margin_percent' => $this->marginPercent,
            'is_enabled' => $this->isEnabled,
            'is_manual_rate' => $this->isManualRate,
        ];
    }
}
