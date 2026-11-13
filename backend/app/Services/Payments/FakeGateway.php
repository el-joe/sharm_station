<?php

namespace App\Services\Payments;

use App\DTOs\Payment\CheckoutSessionData;
use App\DTOs\Payment\WebhookEventData;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Support\Str;

/**
 * Local gateway for development and tests. The "checkout" sends the customer straight back to the booking page;
 * a webhook signed with HMAC-SHA256 (header X-Fake-Signature) confirms or fails the payment.
 *
 * Webhook body: {"type": "payment.succeeded"|"payment.failed", "reference": "<checkout reference>", "transaction_id": "..."}
 */
class FakeGateway implements PaymentGateway
{
    public function __construct(private readonly string $webhookSecret) {}

    public function createCheckout(Booking $booking, Payment $payment, PaymentMethod $method): CheckoutSessionData
    {
        $reference = 'fake_cs_'.Str::lower(Str::random(24));
        $returnUrl = str_replace('{reference}', $booking->reference, (string) config('payments.return_url'));

        return new CheckoutSessionData(
            reference: $reference,
            url: $returnUrl.(str_contains($returnUrl, '?') ? '&' : '?').http_build_query(['payment' => 'fake', 'session' => $reference]),
            expiresAt: now()->addHour()->toIso8601String(),
        );
    }

    public function verifyWebhook(string $payload, ?string $signature): bool
    {
        return $signature !== null && $signature !== '' && hash_equals($this->sign($payload), $signature);
    }

    public function parseWebhook(string $payload): WebhookEventData
    {
        $event = json_decode($payload, true) ?: [];
        $type = (string) ($event['type'] ?? 'unknown');

        return new WebhookEventData(
            type: $type,
            reference: $event['reference'] ?? null,
            succeeded: match ($type) {
                'payment.succeeded' => true,
                'payment.failed' => false,
                default => null,
            },
            transactionId: $event['transaction_id'] ?? null,
        );
    }

    public function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->webhookSecret);
    }
}
