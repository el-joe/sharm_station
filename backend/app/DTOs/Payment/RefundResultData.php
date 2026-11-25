<?php

namespace App\DTOs\Payment;

/**
 * Result of a refund sent to a gateway. $pending: accepted but not settled yet (still counted as refunded).
 */
final readonly class RefundResultData
{
    public function __construct(
        public string $reference,
        public bool $pending = false,
    ) {}
}
