<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Payment\ProofSubmissionData;
use App\Http\Requests\Api\CheckoutRequest;
use App\Http\Requests\Api\StorePaymentProofRequest;
use App\Http\Resources\V1\PaymentMethodResource;
use App\Http\Resources\V1\PaymentProofResource;
use App\Models\Booking;
use App\Models\PaymentMethod;
use App\Services\BookingService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Payment step of a booking (SRS 10). Visible with the booking access token (?token= or "token" field)
 * or to the signed-in owner, partner or staff.
 */
class BookingPaymentController extends ApiController
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly PaymentService $payments,
    ) {}

    public function methods(Request $request, string $reference): JsonResponse
    {
        $booking = $this->authorizedBooking($request, $reference);

        return response()->json([
            'data' => $this->payments->methodsFor($booking)->map(function (PaymentMethod $method) use ($booking, $request): array {
                $resource = new PaymentMethodResource($method);
                $resource->booking = $booking;

                return $resource->resolve($request);
            })->values(),
            'meta' => [
                'reference' => $booking->reference,
                'currency' => $booking->currency,
                'payment_status' => $booking->payment_status->value,
            ],
        ]);
    }

    public function storeProof(StorePaymentProofRequest $request, string $reference): JsonResponse
    {
        $booking = $this->authorizedBooking($request, $reference);
        $proof = $this->payments->submitProof($booking, ProofSubmissionData::fromRequest($request));

        return (new PaymentProofResource($proof))->additional(['meta' => [
            'payment_status' => $booking->fresh()->payment_status->value,
        ]])->response($request)->setStatusCode(201);
    }

    public function checkout(CheckoutRequest $request, string $reference): JsonResponse
    {
        $booking = $this->authorizedBooking($request, $reference);
        $session = $this->payments->startCheckout($booking, (int) $request->validated('payment_method_id'));

        return response()->json(['data' => [
            'checkout_url' => $session->url,
            'session_reference' => $session->reference,
            'expires_at' => $session->expiresAt,
        ]], 201);
    }

    private function authorizedBooking(Request $request, string $reference): Booking
    {
        $booking = $this->bookings->findByReference($reference);
        $token = $request->query('token') ?? $request->input('token');
        abort_if($booking === null || ! $this->bookings->canView($booking, $request->user('sanctum'), is_string($token) ? $token : null), 404, 'Booking not found.');

        return $booking;
    }
}
