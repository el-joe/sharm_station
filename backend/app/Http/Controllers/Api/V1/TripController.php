<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Trip\TripFilterData;
use App\Http\Requests\Api\TripIndexRequest;
use App\Http\Resources\V1\TripCardResource;
use App\Http\Resources\V1\TripResource;
use App\Services\TripService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TripController extends ApiController
{
    public function __construct(private readonly TripService $trips) {}

    public function index(TripIndexRequest $request): AnonymousResourceCollection
    {
        return TripCardResource::collection($this->trips->paginate(TripFilterData::fromRequest($request)));
    }

    public function show(string $slug): TripResource
    {
        $trip = $this->trips->findPublishedBySlug($slug);
        abort_if($trip === null, 404, 'Trip not found.');

        return new TripResource($trip);
    }
}
