<?php

namespace App\Services\Payments;

use App\DTOs\Payment\WebhookEventData;

/**
 * Gateways where an approved order must be captured by the platform (PayPal Orders v2).
 */
interface CapturesPayments
{
    /**
     * @throws PaymentGatewayException
     */
    public function capture(string $reference): WebhookEventData;
}
