<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Partner\PartnerCatalogueFilterData;
use App\Http\Requests\Api\PartnerBookingIndexRequest;
use App\Http\Requests\Api\PartnerBookingRequest;
use App\Http\Requests\Api\PartnerCatalogueRequest;
use App\Http\Requests\Api\PartnerProfileRequest;
use App\Http\Requests\Api\PartnerQuoteRequest;
use App\Http\Resources\V1\BookingResource;
use App\Http\Resources\V1\PartnerProfileResource;
use App\Models\Partner;
use App\Services\CurrencyService;
use App\Services\NationalityService;
use App\Services\PartnerBookingService;
use App\Services\PartnerPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Partner panel API (approved partners only, see EnsureActivePartner).
 */
class PartnerPanelController extends ApiController
{
    public function __construct(
        private readonly PartnerPortalService $portal,
        private readonly PartnerBookingService $partnerBookings,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $dashboard = $this->portal->dashboard($this->activePartner($request));

        return response()->json(['data' => [
            'kpis' => $dashboard['kpis'],
            'commissions' => $dashboard['commissions'],
            'recent_bookings' => BookingResource::collection($dashboard['recent_bookings'])->resolve($request),
        ]]);
    }

    public function bookings(PartnerBookingIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->safe()->only(['service', 'status', 'payment_status']);

        return BookingResource::collection($this->portal->bookings($this->activePartner($request), $filters, (int) $request->validated('per_page', 15)));
    }

    public function statements(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->portal->statements($this->activePartner($request))]);
    }

    public function priceList(PartnerCatalogueRequest $request): JsonResponse
    {
        $partner = $this->activePartner($request);
        abort_unless($partner->price_list_enabled, 403, 'The price list is not enabled for your account.');

        return response()->json(['data' => $this->portal->priceList($partner, PartnerCatalogueFilterData::fromRequest($request))]);
    }

    /**
     * New booking catalogue: customer price for the client's nationality, partner price and commission per item.
     */
    public function catalogue(PartnerCatalogueRequest $request, NationalityService $nationalities): JsonResponse
    {
        $filters = PartnerCatalogueFilterData::fromRequest($request);

        return response()->json([
            'data' => $this->portal->catalogue($this->activePartner($request), $filters),
            'nationality' => ['code' => $filters->nationalityCode, 'group' => $nationalities->groupFor($filters->nationalityCode)->summary()],
        ]);
    }

    public function quote(PartnerQuoteRequest $request, CurrencyService $currencies): JsonResponse
    {
        $quote = $this->partnerBookings->quote($this->activePartner($request), $request->validated(), $currencies->current());

        return $this->quoteResponse($request, $quote);
    }

    /**
     * Creates a booking with the partner as booker and the end client's nationality.
     */
    public function storeBooking(PartnerBookingRequest $request, CurrencyService $currencies): JsonResponse
    {
        $booking = $this->partnerBookings->book($this->activePartner($request), $request->validated(), $currencies->current(), app()->getLocale());

        $resource = new BookingResource($booking);
        $resource->paymentOptions = $this->partnerBookings->paymentOptions($booking);
        $resource->accessToken = $booking->access_token;

        return $resource->additional(['meta' => ['payment_method' => $request->validated('payment_method')]])
            ->response($request)
            ->setStatusCode(201);
    }

    public function profile(Request $request): PartnerProfileResource
    {
        return new PartnerProfileResource($this->activePartner($request)->load('user'));
    }

    public function updateProfile(PartnerProfileRequest $request): PartnerProfileResource
    {
        return new PartnerProfileResource($this->portal->updateProfile($this->activePartner($request), $request->validated())->load('user'));
    }

    private function activePartner(Request $request): Partner
    {
        return $request->attributes->get('active_partner');
    }
}
