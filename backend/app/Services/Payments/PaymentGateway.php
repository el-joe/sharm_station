<?php

namespace App\Services\Payments;

use App\DTOs\Payment\CheckoutSessionData;
use App\DTOs\Payment\WebhookEventData;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;

/**
 * Online gateway driver (SRS 10.2). Card data never reaches the platform: the customer pays on the gateway's
 * hosted checkout and the result arrives through a signed webhook.
 */
interface PaymentGateway
{
    /**
     * @throws PaymentGatewayException when the gateway is not configured or refuses the session.
     */
    public function createCheckout(Booking $booking, Payment $payment, PaymentMethod $method): CheckoutSessionData;

    public function verifyWebhook(string $payload, ?string $signature): bool;

    public function parseWebhook(string $payload): WebhookEventData;
}
