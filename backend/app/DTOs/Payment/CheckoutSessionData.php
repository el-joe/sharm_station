<?php

namespace App\DTOs\Payment;

/**
 * Hosted checkout created by an online gateway.
 */
final readonly class CheckoutSessionData
{
    public function __construct(
        public string $reference,
        public string $url,
        public ?string $expiresAt = null,
    ) {}
}
