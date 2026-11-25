<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Room\RoomCombinationQueryData;
use App\Http\Requests\Api\RoomCombinationRequest;
use App\Services\BuildingService;
use App\Services\RoomCombinationService;
use Illuminate\Http\JsonResponse;

/**
 * Best room combinations of a building for a party and a stay (SRS 6.4), cheapest first.
 */
class RoomCombinationController extends ApiController
{
    public function __invoke(RoomCombinationRequest $request, BuildingService $buildings, RoomCombinationService $combinations): JsonResponse
    {
        $query = RoomCombinationQueryData::fromRequest($request, $this->nationality()[1]);
        $building = $buildings->findPublishedBySlug($query->building);
        abort_if($building === null, 404, 'Building not found.');

        $results = $combinations->best($building, $query);

        return response()->json([
            'data' => $results,
            'meta' => [
                'building' => ['id' => $building->id, 'slug' => $building->slug, 'name' => $building->name],
                'check_in' => $query->checkIn->toDateString(),
                'check_out' => $query->checkOut->toDateString(),
                'nights' => (int) $query->checkIn->diffInDays($query->checkOut),
                'adults' => $query->adults,
                'child_ages' => $query->childAges,
                'count' => count($results),
                'message' => $results === [] ? __('allocation.cannot_fit', ['count' => $query->adults + count($query->childAges)]) : null,
            ],
        ]);
    }
}
