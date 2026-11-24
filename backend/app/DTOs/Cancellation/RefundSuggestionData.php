<?php

namespace App\DTOs\Cancellation;

/**
 * Refund suggested by the cancellation policy time bands (SRS 12.4); the admin confirms or edits it.
 */
final readonly class RefundSuggestionData
{
    public function __construct(
        public ?int $daysBefore,
        public float $percent,
        public string $rule,
        public float $paid,
        public float $refund,
    ) {}

    /**
     * @return array{days_before: int|null, percent: float, rule: string, paid: float, refund: float}
     */
    public function toArray(): array
    {
        return [
            'days_before' => $this->daysBefore,
            'percent' => $this->percent,
            'rule' => $this->rule,
            'paid' => $this->paid,
            'refund' => $this->refund,
        ];
    }
}
