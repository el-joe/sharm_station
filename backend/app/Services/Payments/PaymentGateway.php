<?php

namespace App\Services\Payments;

use App\DTOs\Payment\CheckoutSessionData;
use App\DTOs\Payment\RefundResultData;
use App\DTOs\Payment\WebhookEventData;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;

/**
 * Online gateway driver (SRS 10.2). Card data never reaches the platform: the customer pays on the gateway's
 * hosted checkout and the result arrives through a signed webhook (or the return capture for PayPal).
 */
interface PaymentGateway
{
    /**
     * Whether the keys the driver needs are set (gateways without keys are hidden from customers).
     */
    public function isConfigured(): bool;

    /**
     * @throws PaymentGatewayException when the gateway is not configured or refuses the session.
     */
    public function createCheckout(Booking $booking, Payment $payment, PaymentMethod $method): CheckoutSessionData;

    /**
     * @param  array<string, string>  $headers  request headers, lower-case names
     */
    public function verifyWebhook(string $payload, array $headers): bool;

    public function parseWebhook(string $payload): WebhookEventData;

    public function supportsRefunds(): bool;

    /**
     * Refunds part or all of a succeeded payment, amount in the payment currency.
     *
     * @throws PaymentGatewayException when the gateway refuses the refund.
     */
    public function refund(Payment $payment, float $amountInCurrency): RefundResultData;
}
