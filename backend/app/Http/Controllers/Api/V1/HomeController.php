<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\DestinationResource;
use App\Http\Resources\V1\GoogleReviewResource;
use App\Http\Resources\V1\PackageCardResource;
use App\Http\Resources\V1\RoomCardResource;
use App\Http\Resources\V1\TripCardResource;
use App\Services\HomeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends ApiController
{
    public function __invoke(Request $request, HomeService $home): JsonResponse
    {
        $data = $home->home();

        return response()->json(['data' => [
            'stats' => $data['stats'],
            'featured_trips' => TripCardResource::collection($data['featured_trips'])->resolve($request),
            'nile_trips' => TripCardResource::collection($data['nile_trips'])->resolve($request),
            'packages' => PackageCardResource::collection($data['packages'])->resolve($request),
            'rooms' => RoomCardResource::collection($data['rooms'])->resolve($request),
            'destinations' => DestinationResource::collection($data['destinations'])->resolve($request),
            'google_reviews' => [
                'rating' => $data['reviews']['rating'],
                'total' => $data['reviews']['total'],
                'write_review_url' => $data['reviews']['write_review_url'],
                'reviews' => GoogleReviewResource::collection($data['reviews']['reviews'])->resolve($request),
            ],
        ]]);
    }
}
