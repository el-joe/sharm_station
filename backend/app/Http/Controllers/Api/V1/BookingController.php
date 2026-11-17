<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Booking\CreateBookingData;
use App\Http\Requests\Api\StoreBookingRequest;
use App\Http\Resources\V1\BookingResource;
use App\Services\BookingService;
use App\Services\CurrencyService;
use App\Services\NationalityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends ApiController
{
    public function __construct(private readonly BookingService $bookings) {}

    /**
     * Public checkout: always at customer prices, never as a partner booking (CR-15).
     */
    public function store(StoreBookingRequest $request, CurrencyService $currencies, NationalityService $nationalities): JsonResponse
    {
        $groupId = $nationalities->groupFor($request->validated('nationality_code'))->id;

        $booking = $this->bookings->createFromQuote(
            CreateBookingData::fromRequest($request, null, $currencies->current(), $groupId),
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
