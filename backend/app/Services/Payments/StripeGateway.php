<?php

namespace App\Services\Payments;

use App\DTOs\Payment\CheckoutSessionData;
use App\DTOs\Payment\RefundResultData;
use App\DTOs\Payment\WebhookEventData;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\WebhookSignature;
use UnexpectedValueException;

/**
 * Stripe Checkout driver (official SDK). Enabled when STRIPE_SECRET is set; test mode = a sk_test_ key.
 * The session charges the amount due now (deposit or balance) in the booking currency; the signed webhook
 * (STRIPE_WEBHOOK_SECRET, Stripe-Signature header) confirms it. Refunds go through the Refunds API.
 */
class StripeGateway implements PaymentGateway
{
    private ?StripeClient $client = null;

    public function __construct(
        private readonly ?string $secret,
        private readonly ?string $webhookSecret,
        private readonly ?string $apiBase = null,
        private readonly int $toleranceSeconds = 300,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->secret);
    }

    public function createCheckout(Booking $booking, Payment $payment, PaymentMethod $method): CheckoutSessionData
    {
        if (! $this->isConfigured()) {
            throw PaymentGatewayException::withErrors(['payment_method_id' => ['Card payments are not configured yet. Choose another payment method.']]);
        }

        try {
            $session = $this->client()->checkout->sessions->create([
                'mode' => 'payment',
                'success_url' => ReturnUrls::success($booking),
                'cancel_url' => ReturnUrls::cancel($booking),
                'client_reference_id' => $booking->reference,
                'customer_email' => $booking->lead_email ?: null,
                'locale' => in_array($booking->locale, ['en', 'it'], true) ? $booking->locale : 'auto',
                'metadata' => ['booking_reference' => $booking->reference, 'payment_id' => (string) $payment->id],
                'payment_intent_data' => ['metadata' => ['booking_reference' => $booking->reference, 'payment_id' => (string) $payment->id]],
                'expires_at' => now()->addMinutes(60)->getTimestamp(),
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($payment->currency),
                        'unit_amount' => self::toMinorUnits((float) $payment->amount_in_currency),
                        'product_data' => ['name' => "Booking {$booking->reference} ({$payment->type->label()})"],
                    ],
                ]],
            ]);
        } catch (ApiErrorException $exception) {
            report($exception);

            throw PaymentGatewayException::withErrors(['payment_method_id' => ['The card payment could not be started. Please try again.']]);
        }

        if (blank($session->id) || blank($session->url)) {
            throw PaymentGatewayException::withErrors(['payment_method_id' => ['The card payment could not be started. Please try again.']]);
        }

        return new CheckoutSessionData((string) $session->id, (string) $session->url, isset($session->expires_at) ? date(DATE_ATOM, (int) $session->expires_at) : null);
    }

    public function verifyWebhook(string $payload, array $headers): bool
    {
        $signature = $headers['stripe-signature'] ?? '';

        if (blank($this->webhookSecret) || $signature === '') {
            return false;
        }

        try {
            return WebhookSignature::verifyHeader($payload, $signature, (string) $this->webhookSecret, $this->toleranceSeconds);
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return false;
        }
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
                'checkout.session.completed' => ($object['payment_status'] ?? 'paid') === 'paid' ? true : null,
                'checkout.session.async_payment_succeeded' => true,
                'checkout.session.expired', 'checkout.session.async_payment_failed' => false,
                default => null,
            },
            transactionId: $object['payment_intent'] ?? null,
        );
    }

    public function supportsRefunds(): bool
    {
        return $this->isConfigured();
    }

    public function refund(Payment $payment, float $amountInCurrency): RefundResultData
    {
        if (! $this->isConfigured() || blank($payment->transaction_id)) {
            throw PaymentGatewayException::withErrors(['refund' => ['This card payment cannot be refunded online.']]);
        }

        try {
            $refund = $this->client()->refunds->create([
                'payment_intent' => $payment->transaction_id,
                'amount' => self::toMinorUnits($amountInCurrency),
                'metadata' => ['payment_id' => (string) $payment->id],
            ]);
        } catch (ApiErrorException $exception) {
            report($exception);

            throw PaymentGatewayException::withErrors(['refund' => ['Stripe refused the refund: '.$exception->getMessage()]]);
        }

        return new RefundResultData((string) $refund->id, in_array($refund->status, ['pending', 'requires_action'], true));
    }

    public static function toMinorUnits(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function client(): StripeClient
    {
        return $this->client ??= new StripeClient(array_filter([
            'api_key' => (string) $this->secret,
            'api_base' => $this->apiBase,
        ]));
    }
}
