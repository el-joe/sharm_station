<?php

namespace App\Services\Payments;

use App\DTOs\Payment\CheckoutSessionData;
use App\DTOs\Payment\WebhookEventData;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Http;

/**
 * Stripe Checkout driver skeleton. It only calls Stripe when a secret key is configured (STRIPE_SECRET);
 * webhooks are verified with the Stripe-Signature scheme (t=timestamp, v1=HMAC-SHA256 of "t.payload").
 */
class StripeGateway implements PaymentGateway
{
    public function __construct(
        private readonly ?string $secret,
        private readonly ?string $webhookSecret,
        private readonly string $apiBase = 'https://api.stripe.com/v1',
        private readonly int $toleranceSeconds = 300,
    ) {}

    public function createCheckout(Booking $booking, Payment $payment, PaymentMethod $method): CheckoutSessionData
    {
        if (blank($this->secret)) {
            throw PaymentGatewayException::withErrors(['payment_method_id' => ['Card payments are not configured yet. Choose another payment method.']]);
        }

        $returnUrl = str_replace('{reference}', $booking->reference, (string) config('payments.return_url'));
        $response = Http::asForm()->withToken((string) $this->secret)->post($this->apiBase.'/checkout/sessions', [
            'mode' => 'payment',
            'success_url' => $returnUrl.'?payment=success',
            'cancel_url' => $returnUrl.'?payment=cancelled',
            'client_reference_id' => $booking->reference,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($payment->currency),
                    'unit_amount' => (int) round((float) $payment->amount_in_currency * 100),
                    'product_data' => ['name' => "Booking {$booking->reference} ({$payment->type->label()})"],
                ],
            ]],
        ]);

        if ($response->failed() || blank($response->json('id'))) {
            throw PaymentGatewayException::withErrors(['payment_method_id' => ['The card payment could not be started. Please try again.']]);
        }

        return new CheckoutSessionData((string) $response->json('id'), (string) $response->json('url'));
    }

    public function verifyWebhook(string $payload, ?string $signature): bool
    {
        if (blank($this->webhookSecret) || blank($signature)) {
            return false;
        }

        $parts = [];

        foreach (explode(',', (string) $signature) as $item) {
            [$key, $value] = array_pad(explode('=', trim($item), 2), 2, null);
            $parts[$key][] = $value;
        }

        $timestamp = (int) ($parts['t'][0] ?? 0);

        if ($timestamp === 0 || abs(now()->getTimestamp() - $timestamp) > $this->toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, (string) $this->webhookSecret);

        foreach ($parts['v1'] ?? [] as $candidate) {
            if (is_string($candidate) && hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }

    public function parseWebhook(string $payload): WebhookEventData
    {
        $event = json_decode($payload, true) ?: [];
        $type = (string) ($event['type'] ?? 'unknown');
        $object = $event['data']['object'] ?? [];

        return new WebhookEventData(
            type: $type,
            reference: $object['id'] ?? null,
            succeeded: match ($type) {
                'checkout.session.completed' => ($object['payment_status'] ?? 'paid') === 'paid',
                'checkout.session.expired', 'checkout.session.async_payment_failed' => false,
                default => null,
            },
            transactionId: $object['payment_intent'] ?? null,
        );
    }
}
