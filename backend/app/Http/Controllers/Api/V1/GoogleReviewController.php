<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\GoogleReviewResource;
use App\Services\GoogleReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoogleReviewController extends ApiController
{
    public function __invoke(Request $request, GoogleReviewService $reviews): JsonResponse
    {
        $limit = min(max((int) $request->integer('limit', 10), 1), 50);
        $summary = $reviews->summary(limit: $limit);

        return response()->json(['data' => [
            'place_id' => $summary['place_id'],
            'rating' => $summary['rating'],
            'total' => $summary['total'],
            'write_review_url' => $summary['write_review_url'],
            'maps_url' => $summary['maps_url'],
            'synced_at' => $summary['synced_at'],
            'reviews' => GoogleReviewResource::collection($summary['reviews'])->resolve($request),
        ]]);
    }
}
