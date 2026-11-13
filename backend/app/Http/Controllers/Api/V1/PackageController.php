<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Package\PackageFilterData;
use App\Http\Requests\Api\PackageIndexRequest;
use App\Http\Resources\V1\PackageCardResource;
use App\Http\Resources\V1\PackageResource;
use App\Services\PackageService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PackageController extends ApiController
{
    public function __construct(private readonly PackageService $packages) {}

    public function index(PackageIndexRequest $request): AnonymousResourceCollection
    {
        return PackageCardResource::collection($this->packages->paginate(PackageFilterData::fromRequest($request)));
    }

    public function show(string $slug): PackageResource
    {
        $package = $this->packages->findPublishedBySlug($slug);
        abort_if($package === null, 404, 'Package not found.');

        return new PackageResource($package);
    }
}
