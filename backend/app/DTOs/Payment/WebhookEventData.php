<?php

namespace App\DTOs\Payment;

/**
 * Normalised gateway notification. $succeeded is null for events that do not change a payment.
 */
final readonly class WebhookEventData
{
    public function __construct(
        public string $type,
        public ?string $reference,
        public ?bool $succeeded,
        public ?string $transactionId = null,
    ) {}
}
