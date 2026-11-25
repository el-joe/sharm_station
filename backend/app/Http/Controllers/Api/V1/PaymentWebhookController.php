<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Gateway webhooks: POST /api/v1/payments/webhooks/{driver} (fake: X-Fake-Signature, stripe: Stripe-Signature,
 * paypal: PAYPAL-TRANSMISSION-* headers verified by PayPal), and the PayPal return URL that captures the order.
 */
class PaymentWebhookController extends ApiController
{
    public function __construct(private readonly PaymentService $payments) {}

    public function __invoke(Request $request, string $driver): JsonResponse
    {
        $headers = collect($request->headers->all())
            ->mapWithKeys(fn (array $values, string $name): array => [strtolower($name) => (string) ($values[0] ?? '')])
            ->all();

        return $this->payments->handleWebhook($driver, $request->getContent(), $headers)
            ? response()->json(['received' => true])
            : response()->json(['message' => 'Invalid signature.'], 400);
    }

    /**
     * GET /api/v1/payments/paypal/return?payment={id}&token={order id}: captures, then redirects to the booking page.
     */
    public function paypalReturn(Request $request): RedirectResponse
    {
        $token = $request->query('token');

        return redirect()->away($this->payments->completeReturn((int) $request->query('payment'), is_string($token) ? $token : null));
    }
}
