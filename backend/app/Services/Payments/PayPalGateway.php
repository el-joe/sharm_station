<?php

namespace App\Services\Payments;

use App\DTOs\Payment\CheckoutSessionData;
use App\DTOs\Payment\RefundResultData;
use App\DTOs\Payment\WebhookEventData;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * PayPal Orders v2 driver over the REST API (no SDK). Enabled when PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET are set;
 * PAYPAL_MODE=sandbox|live picks the API host. The buyer approves the order on PayPal and comes back to
 * /api/v1/payments/paypal/return, which captures it; the CHECKOUT.ORDER.APPROVED / PAYMENT.CAPTURE.* webhooks
 * (verified with PayPal's verify-webhook-signature endpoint and PAYPAL_WEBHOOK_ID) do the same if the buyer never returns.
 */
class PayPalGateway implements CapturesPayments, PaymentGateway
{
    public const HOSTS = ['sandbox' => 'https://api-m.sandbox.paypal.com', 'live' => 'https://api-m.paypal.com'];

    public const SIGNATURE_HEADERS = ['paypal-auth-algo', 'paypal-cert-url', 'paypal-transmission-id', 'paypal-transmission-sig', 'paypal-transmission-time'];

    public function __construct(
        private readonly ?string $clientId,
        private readonly ?string $clientSecret,
        private readonly string $mode = 'sandbox',
        private readonly ?string $webhookId = null,
        private readonly int $timeout = 15,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->clientId) && filled($this->clientSecret);
    }

    public function baseUrl(): string
    {
        return self::HOSTS[$this->mode] ?? self::HOSTS['sandbox'];
    }

    public function createCheckout(Booking $booking, Payment $payment, PaymentMethod $method): CheckoutSessionData
    {
        if (! $this->isConfigured()) {
            throw PaymentGatewayException::withErrors(['payment_method_id' => ['PayPal is not configured yet. Choose another payment method.']]);
        }

        $response = $this->api()->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $payment->id,
                'custom_id' => $booking->reference,
                'invoice_id' => $booking->reference.'-'.$payment->id,
                'description' => "Booking {$booking->reference} ({$payment->type->label()})",
                'amount' => ['currency_code' => strtoupper($payment->currency), 'value' => self::formatAmount((float) $payment->amount_in_currency)],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'brand_name' => (string) config('app.name'),
                'locale' => $booking->locale === 'it' ? 'it-IT' : 'en-GB',
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
                'return_url' => route('api.v1.payments.paypal.return', ['payment' => $payment->id]),
                'cancel_url' => ReturnUrls::cancel($booking),
            ]]],
        ]);

        $approveUrl = collect($response->json('links', []))->first(fn (array $link): bool => in_array($link['rel'] ?? '', ['payer-action', 'approve'], true))['href'] ?? null;

        if ($response->failed() || blank($response->json('id')) || blank($approveUrl)) {
            report(new PaymentGatewayException('PayPal order creation failed: '.$response->body()));

            throw PaymentGatewayException::withErrors(['payment_method_id' => ['The PayPal payment could not be started. Please try again.']]);
        }

        return new CheckoutSessionData((string) $response->json('id'), (string) $approveUrl);
    }

    public function capture(string $reference): WebhookEventData
    {
        $response = $this->api()->withHeaders(['PayPal-Request-Id' => 'capture-'.$reference])
            ->withBody('{}', 'application/json')
            ->post('/v2/checkout/orders/'.rawurlencode($reference).'/capture');

        // Already captured (e.g. by the webhook): read the order instead.
        if ($response->status() === 422 && str_contains($response->body(), 'ORDER_ALREADY_CAPTURED')) {
            $response = $this->api()->get('/v2/checkout/orders/'.rawurlencode($reference));
        }

        $capture = $response->json('purchase_units.0.payments.captures.0');
        $status = (string) ($capture['status'] ?? $response->json('status', ''));

        return new WebhookEventData(
            type: 'capture',
            reference: $reference,
            succeeded: match (true) {
                $response->failed() => false,
                in_array($status, ['COMPLETED'], true) => true,
                in_array($status, ['DECLINED', 'FAILED', 'VOIDED'], true) => false,
                default => null,
            },
            transactionId: $capture['id'] ?? null,
        );
    }

    public function verifyWebhook(string $payload, array $headers): bool
    {
        if (! $this->isConfigured() || blank($this->webhookId)) {
            return false;
        }

        foreach (self::SIGNATURE_HEADERS as $header) {
            if (blank($headers[$header] ?? null)) {
                return false;
            }
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return false;
        }

        $response = $this->api()->post('/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $headers['paypal-auth-algo'],
            'cert_url' => $headers['paypal-cert-url'],
            'transmission_id' => $headers['paypal-transmission-id'],
            'transmission_sig' => $headers['paypal-transmission-sig'],
            'transmission_time' => $headers['paypal-transmission-time'],
            'webhook_id' => $this->webhookId,
            'webhook_event' => $event,
        ]);

        return $response->successful() && $response->json('verification_status') === 'SUCCESS';
    }

    public function parseWebhook(string $payload): WebhookEventData
    {
        $event = json_decode($payload, true) ?: [];
        $type = (string) ($event['event_type'] ?? 'unknown');
        $resource = $event['resource'] ?? [];

        return match ($type) {
            'CHECKOUT.ORDER.APPROVED' => new WebhookEventData($type, $resource['id'] ?? null, null, needsCapture: true),
            'PAYMENT.CAPTURE.COMPLETED' => new WebhookEventData($type, $resource['supplementary_data']['related_ids']['order_id'] ?? null, true, $resource['id'] ?? null),
            'PAYMENT.CAPTURE.DENIED', 'PAYMENT.CAPTURE.DECLINED' => new WebhookEventData($type, $resource['supplementary_data']['related_ids']['order_id'] ?? null, false, $resource['id'] ?? null),
            default => new WebhookEventData($type, null, null),
        };
    }

    public function supportsRefunds(): bool
    {
        return $this->isConfigured();
    }

    public function refund(Payment $payment, float $amountInCurrency): RefundResultData
    {
        if (! $this->isConfigured() || blank($payment->transaction_id)) {
            throw PaymentGatewayException::withErrors(['refund' => ['This PayPal payment cannot be refunded online.']]);
        }

        $response = $this->api()->withHeaders(['PayPal-Request-Id' => 'refund-'.$payment->id.'-'.md5((string) $amountInCurrency.microtime())])
            ->post('/v2/payments/captures/'.rawurlencode((string) $payment->transaction_id).'/refund', [
                'amount' => ['currency_code' => strtoupper($payment->currency), 'value' => self::formatAmount($amountInCurrency)],
                'note_to_payer' => 'Refund for booking '.$payment->booking?->reference,
            ]);

        if ($response->failed() || blank($response->json('id'))) {
            throw PaymentGatewayException::withErrors(['refund' => ['PayPal refused the refund: '.($response->json('message') ?? $response->status())]]);
        }

        return new RefundResultData((string) $response->json('id'), $response->json('status') === 'PENDING');
    }

    public static function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function api(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->withToken($this->accessToken());
    }

    /**
     * OAuth client-credentials token, cached until shortly before it expires.
     */
    private function accessToken(): string
    {
        $key = 'paypal.token.'.md5($this->mode.'|'.$this->clientId);

        return Cache::remember($key, now()->addMinutes(30), function (): string {
            $response = Http::baseUrl($this->baseUrl())
                ->timeout($this->timeout)
                ->asForm()
                ->withBasicAuth((string) $this->clientId, (string) $this->clientSecret)
                ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials']);

            if ($response->failed() || blank($response->json('access_token'))) {
                throw PaymentGatewayException::withErrors(['payment_method_id' => ['PayPal is not available right now. Please try again later.']]);
            }

            return (string) $response->json('access_token');
        });
    }
}
