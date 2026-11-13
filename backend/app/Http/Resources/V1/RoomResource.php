<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\V1\Concerns\PresentsPrices;
use App\Models\ChildAgeBand;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Room detail with price list, building card and other rooms in the building.
 *
 * @mixin Room
 */
class RoomResource extends JsonResource
{
    use PresentsPrices;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $policy = $this->childPolicy ?? $this->building->childPolicy;

        return [
            ...(new RoomCardResource($this->resource))->toArray($request),
            'description' => $this->description,
            'extra_bed_price' => $this->money($this->extra_bed_price),
            'price_list' => $this->priceList($this->resource),
            'child_policy' => $policy ? [
                'children_allowed' => $policy->children_allowed,
                'adult_from_age' => $policy->adult_from_age,
                'bands' => $policy->bands->map(fn (ChildAgeBand $band): array => [
                    'min_age' => $band->min_age,
                    'max_age' => $band->max_age,
                    'discount_type' => $band->discount_type->value,
                    'label' => $band->label(),
                ])->values(),
            ] : null,
            'building_details' => [
                'check_in_from' => $this->building->check_in_from ? substr((string) $this->building->check_in_from, 0, 5) : null,
                'check_out_until' => $this->building->check_out_until ? substr((string) $this->building->check_out_until, 0, 5) : null,
                'amenities' => $this->building->amenities ?? [],
                'cancellation_policy' => $this->building->cancellation_policy,
                'deposit_percent' => (float) $this->building->deposit_percent,
            ],
            'other_rooms' => RoomCardResource::collection($this->whenLoaded('siblings')),
        ];
    }
}
