<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\CancellationRequestRequest;
use App\Http\Resources\V1\BookingResource;
use App\Services\BookingService;
use App\Services\CancellationService;
use Illuminate\Http\JsonResponse;

class CancellationRequestController extends ApiController
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly CancellationService $cancellations,
    ) {}

    /**
     * Customer, partner or booking-link holder asks to cancel: 201 when recorded, 200 when it was already requested.
     */
    public function __invoke(CancellationRequestRequest $request, string $reference): JsonResponse
    {
        $booking = $this->bookings->findByReference($reference);
        $user = $request->user('sanctum');
        abort_if($booking === null || ! $this->bookings->canView($booking, $user, $request->accessToken()), 404, 'Booking not found.');

        ['booking' => $booking, 'created' => $created] = $this->cancellations->request($booking, $request->validated('reason'), $user);

        return (new BookingResource($booking->fresh()))
            ->additional(['message' => $created ? 'Your cancellation request has been sent. We will confirm the refund by email.' : 'A cancellation request is already open for this booking.'])
            ->response($request)
            ->setStatusCode($created ? 201 : 200);
    }
}
