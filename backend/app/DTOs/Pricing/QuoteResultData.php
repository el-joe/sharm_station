<?php

namespace App\DTOs\Pricing;

use App\Enums\PriceRule;
use App\Enums\ServiceType;

/**
 * Result of a trip or room quote. All amounts are EUR unless stated; "converted"
 * holds the totals in the requested currency.
 */
final readonly class QuoteResultData
{
    /**
     * @param  list<QuoteLineData>  $lines
     * @param  array<string, list<string>>  $errors
     * @param  array<string, mixed>  $context  pricing_mode, booking_type, travel_date, nights, tax_included...
     * @param  array{total: float, deposit: float, balance: float}  $converted
     * @param  array{code: string|null, group: array{id: int, code: string, name: string, is_default: bool}}|null  $nationality
     */
    public function __construct(
        public ServiceType $serviceType,
        public array $lines,
        public ?string $seasonName,
        public float $retailTotal,
        public ?float $partnerTotal,
        public float $commissionAmount,
        public PriceRule $priceRule,
        public ?float $commissionPercent,
        public float $total,
        public float $depositPercent,
        public float $depositAmount,
        public float $balanceAmount,
        public string $currency,
        public float $exchangeRate,
        public array $converted,
        public array $errors = [],
        public array $context = [],
        public ?array $nationality = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            serviceType: ServiceType::from($data['service_type']),
            lines: array_map(QuoteLineData::fromArray(...), $data['lines'] ?? []),
            seasonName: $data['season_name'] ?? null,
            retailTotal: (float) $data['retail_total'],
            partnerTotal: isset($data['partner_total']) ? (float) $data['partner_total'] : null,
            commissionAmount: (float) ($data['commission_amount'] ?? 0),
            priceRule: PriceRule::tryFrom((string) ($data['price_rule'] ?? '')) ?? PriceRule::None,
            commissionPercent: isset($data['commission_percent']) ? (float) $data['commission_percent'] : null,
            total: (float) $data['total'],
            depositPercent: (float) $data['deposit_percent'],
            depositAmount: (float) $data['deposit_amount'],
            balanceAmount: (float) $data['balance_amount'],
            currency: $data['currency'] ?? 'EUR',
            exchangeRate: (float) ($data['exchange_rate'] ?? 1),
            converted: $data['converted'] ?? ['total' => (float) $data['total'], 'deposit' => (float) $data['deposit_amount'], 'balance' => (float) $data['balance_amount']],
            errors: $data['errors'] ?? [],
            context: $data['context'] ?? [],
            nationality: $data['nationality'] ?? null,
        );
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function isPartnerQuote(): bool
    {
        return $this->partnerTotal !== null;
    }

    /**
     * @return list<QuoteLineData>
     */
    public function linesOfType(string $type): array
    {
        return array_values(array_filter($this->lines, fn (QuoteLineData $line): bool => $line->type === $type));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $includePartner = false): array
    {
        $result = [
            'valid' => $this->isValid(),
            'service_type' => $this->serviceType->value,
            ...$this->context,
            'nationality' => $this->nationality,
            'season_name' => $this->seasonName,
            'lines' => array_map(fn (QuoteLineData $line): array => $line->toArray($includePartner), $this->lines),
            'retail_total' => $this->retailTotal,
            'total' => $this->total,
            'deposit_percent' => $this->depositPercent,
            'deposit_amount' => $this->depositAmount,
            'balance_amount' => $this->balanceAmount,
            'base_currency' => 'EUR',
            'currency' => $this->currency,
            'exchange_rate' => $this->exchangeRate,
            'converted' => $this->converted,
            'errors' => (object) $this->errors,
        ];

        if ($includePartner) {
            $result['partner_total'] = $this->partnerTotal;
            $result['commission_amount'] = $this->commissionAmount;
            $result['commission_percent'] = $this->commissionPercent;
            $result['commission_source'] = $this->priceRule->value;
            $result['price_rule'] = $this->priceRule->value;
        }

        return $result;
    }
}
