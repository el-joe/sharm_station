<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gateway webhooks: POST /api/v1/payments/webhooks/{driver}. The signature header depends on the driver.
 */
class PaymentWebhookController extends ApiController
{
    public const SIGNATURE_HEADERS = ['stripe' => 'Stripe-Signature', 'fake' => 'X-Fake-Signature'];

    public function __invoke(Request $request, string $driver, PaymentService $payments): JsonResponse
    {
        $signature = $request->header(self::SIGNATURE_HEADERS[$driver] ?? 'X-Signature');
        $handled = $payments->handleWebhook($driver, $request->getContent(), is_string($signature) ? $signature : null);

        return $handled
            ? response()->json(['received' => true])
            : response()->json(['message' => 'Invalid signature.'], 400);
    }
}
