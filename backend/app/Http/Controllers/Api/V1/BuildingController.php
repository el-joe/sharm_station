<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\BuildingResource;
use App\Services\BuildingService;
use App\Services\SlugRedirectService;

class BuildingController extends ApiController
{
    public function __construct(private readonly BuildingService $buildings) {}

    public function show(string $slug, SlugRedirectService $redirects): BuildingResource
    {
        $building = $this->buildings->findPublishedBySlug($slug);
        if ($building === null) {
            throw $redirects->notFound('building', $slug, 'Building not found.');
        }

        return new BuildingResource($building);
    }
}
