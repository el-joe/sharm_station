<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\PartnerBookingIndexRequest;
use App\Http\Requests\Api\PartnerProfileRequest;
use App\Http\Resources\V1\BookingResource;
use App\Http\Resources\V1\PartnerProfileResource;
use App\Models\Partner;
use App\Services\PartnerPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Partner panel API (approved partners only, see EnsureActivePartner).
 */
class PartnerPanelController extends ApiController
{
    public function __construct(private readonly PartnerPortalService $portal) {}

    public function dashboard(Request $request): JsonResponse
    {
        $dashboard = $this->portal->dashboard($this->activePartner($request));

        return response()->json(['data' => [
            'kpis' => $dashboard['kpis'],
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

    public function priceList(Request $request): JsonResponse
    {
        $partner = $this->activePartner($request);
        abort_unless($partner->price_list_enabled, 403, 'The price list is not enabled for your account.');

        return response()->json(['data' => $this->portal->priceList($partner)]);
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
