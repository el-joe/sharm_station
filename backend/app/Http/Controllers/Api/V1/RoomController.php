<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Room\RoomFilterData;
use App\Http\Requests\Api\RoomIndexRequest;
use App\Http\Resources\V1\RoomCardResource;
use App\Http\Resources\V1\RoomResource;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoomController extends ApiController
{
    public function __construct(private readonly RoomService $rooms) {}

    /**
     * Room cards; with dates or guests every room carries "available" and "unavailable_reason" (unavailable rooms
     * are listed after the bookable ones unless only_available=1). group_by=building groups the page by building.
     */
    public function index(RoomIndexRequest $request): AnonymousResourceCollection|JsonResponse
    {
        $filters = RoomFilterData::fromRequest($request);
        $cards = RoomCardResource::collection($this->rooms->paginate($filters));

        if ($filters->groupBy !== 'building') {
            return $cards;
        }

        $payload = $cards->response($request)->getData(true);
        $payload['data'] = collect($payload['data'])
            ->groupBy('building.id')
            ->map(fn ($rooms): array => [
                'building' => $rooms->first()['building'],
                'available_count' => $rooms->where('available', true)->count(),
                'rooms' => $rooms->values()->all(),
            ])
            ->values()
            ->all();

        return response()->json($payload);
    }

    public function show(int $id): RoomResource
    {
        $room = $this->rooms->findBookable($id);
        abort_if($room === null, 404, 'Room not found.');

        $room->setRelation('siblings', $this->rooms->siblings($room));

        return new RoomResource($room);
    }
}
