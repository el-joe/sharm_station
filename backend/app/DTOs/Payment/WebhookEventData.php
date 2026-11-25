<?php

namespace App\DTOs\Payment;

/**
 * Normalised gateway notification. $succeeded is null for events that do not change a payment;
 * $needsCapture asks the gateway to capture an approved order first (PayPal).
 */
final readonly class WebhookEventData
{
    public function __construct(
        public string $type,
        public ?string $reference,
        public ?bool $succeeded,
        public ?string $transactionId = null,
        public bool $needsCapture = false,
    ) {}
}
