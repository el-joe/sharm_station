<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Room\RoomFilterData;
use App\Http\Requests\Api\RoomIndexRequest;
use App\Http\Resources\V1\RoomCardResource;
use App\Http\Resources\V1\RoomResource;
use App\Services\RoomService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoomController extends ApiController
{
    public function __construct(private readonly RoomService $rooms) {}

    public function index(RoomIndexRequest $request): AnonymousResourceCollection
    {
        return RoomCardResource::collection($this->rooms->paginate(RoomFilterData::fromRequest($request)));
    }

    public function show(int $id): RoomResource
    {
        $room = $this->rooms->findBookable($id);
        abort_if($room === null, 404, 'Room not found.');

        $room->setRelation('siblings', $this->rooms->siblings($room));

        return new RoomResource($room);
    }
}
