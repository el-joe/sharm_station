<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\DestinationResource;
use App\Services\DestinationService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DestinationController extends ApiController
{
    public function __construct(private readonly DestinationService $destinations) {}

    public function index(): AnonymousResourceCollection
    {
        return DestinationResource::collection($this->destinations->active());
    }

    public function show(string $slug): DestinationResource
    {
        $destination = $this->destinations->findActiveBySlug($slug);
        abort_if($destination === null, 404, 'Destination not found.');

        return new DestinationResource($destination);
    }
}
