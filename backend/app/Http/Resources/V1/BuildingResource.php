<?php

namespace App\Http\Resources\V1;

use App\Models\Building;
use App\Services\GoogleReviewService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Building page: details, policies, rooms and Google reviews.
 *
 * @mixin Building
 */
class BuildingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reviews = $this->google_place_id ? app(GoogleReviewService::class)->summary($this->google_place_id, 6) : null;

        return [
            ...(new BuildingCardResource($this->resource))->toArray($request),
            'description' => $this->description,
            'amenities' => $this->amenities ?? [],
            'check_in_from' => $this->check_in_from ? substr((string) $this->check_in_from, 0, 5) : null,
            'check_out_until' => $this->check_out_until ? substr((string) $this->check_out_until, 0, 5) : null,
            'cancellation_policy' => $this->cancellation_policy,
            'deposit_percent' => (float) $this->deposit_percent,
            'google_reviews' => $reviews ? [
                'rating' => $reviews['rating'],
                'total' => $reviews['total'],
                'write_review_url' => $reviews['write_review_url'],
                'reviews' => GoogleReviewResource::collection($reviews['reviews']),
            ] : null,
            'rooms' => $this->rooms->map(function ($room) use ($request): array {
                $room->setRelation('building', $this->resource);

                return (new RoomCardResource($room))->toArray($request);
            })->values(),
        ];
    }
}
