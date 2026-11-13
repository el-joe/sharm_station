<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\BuildingResource;
use App\Services\BuildingService;

class BuildingController extends ApiController
{
    public function __construct(private readonly BuildingService $buildings) {}

    public function show(string $slug): BuildingResource
    {
        $building = $this->buildings->findPublishedBySlug($slug);
        abort_if($building === null, 404, 'Building not found.');

        return new BuildingResource($building);
    }
}
