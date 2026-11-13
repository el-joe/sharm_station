<?php

namespace App\Http\Resources\V1;

use App\Models\Destination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Destination
 */
class DestinationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'trips_count' => $this->whenCounted('trips'),
            'trips' => TripCardResource::collection($this->whenLoaded('trips')),
            'buildings' => BuildingCardResource::collection($this->whenLoaded('buildings')),
        ];
    }
}
