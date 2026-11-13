<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Booking\CreateBookingData;
use App\Http\Requests\Api\StoreBookingRequest;
use App\Http\Resources\V1\BookingResource;
use App\Services\BookingService;
use App\Services\CurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends ApiController
{
    public function __construct(private readonly BookingService $bookings) {}

    public function store(StoreBookingRequest $request, CurrencyService $currencies): JsonResponse
    {
        $booking = $this->bookings->createFromQuote(
            CreateBookingData::fromRequest($request, $this->partner($request), $currencies->current()),
        );

        $resource = new BookingResource($booking);
        $resource->paymentOptions = $this->bookings->paymentOptions($booking);
        $resource->accessToken = $booking->access_token;

        return $resource->response($request)->setStatusCode(201);
    }

    /**
     * Visible with ?token=<access_token>, or to the authenticated owner, partner or staff.
     */
    public function show(Request $request, string $reference): BookingResource
    {
        $booking = $this->bookings->findByReference($reference);
        abort_if($booking === null, 404, 'Booking not found.');
        abort_unless($this->bookings->canView($booking, $request->user('sanctum'), $request->query('token')), 404, 'Booking not found.');

        $resource = new BookingResource($booking);
        $resource->paymentOptions = $this->bookings->paymentOptions($booking);

        return $resource;
    }
}
