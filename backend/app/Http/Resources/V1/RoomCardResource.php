<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\V1\Concerns\PresentsLabels;
use App\Http\Resources\V1\Concerns\PresentsPrices;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Room card: rooms are listed directly with their building's data (SRS 6.3).
 *
 * @mixin Room
 */
class RoomCardResource extends JsonResource
{
    use PresentsLabels, PresentsPrices;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'number' => $this->number,
            'room_type' => $this->room_type->value,
            'capacity' => [
                'max_adults' => $this->max_adults,
                'max_children' => $this->max_children,
                'max_total' => $this->max_total,
            ],
            'beds' => $this->beds,
            'size_m2' => $this->size_m2,
            'view' => $this->view,
            'amenities' => $this->amenities ?? [],
            'cover_image' => $this->cover_image,
            'pricing_mode' => $this->pricing_mode->value,
            'from_price' => $this->groupFromPrice($this->resource, $this->from_price ?? $this->base_price),
            'base_price' => $this->groupBasePrice($this->resource, 'price_per_night'),
            'price_unit' => 'per_night',
            'tax_included' => $this->building->taxes_included,
            'min_nights' => $this->min_nights,
            'extra_beds' => ['max' => $this->max_extra_beds, 'price' => $this->max_extra_beds > 0 ? $this->money($this->extra_bed_price ?? 0) : null],
            'cots' => ['max' => $this->max_cots, 'price' => $this->max_cots > 0 ? $this->money($this->cot_price ?? 0) : null],
            'available' => ($this->resource->getAttribute('unavailable_reasons') ?? []) === [],
            'unavailable_reason' => ($this->resource->getAttribute('unavailable_reasons') ?? [])[0] ?? null,
            'unavailable_reasons' => $this->resource->getAttribute('unavailable_reasons') ?? [],
            'max_nights' => $this->max_nights,
            'building' => new BuildingCardResource($this->building),
            'room_type_label' => $this->catalogLabel('room_types', $this->room_type->value),
            'amenity_labels' => $this->catalogLabels('amenities', $this->amenities),
            'free_cancellation' => $this->building->free_cancellation_hours !== null,
            'free_cancellation_hours' => $this->building->free_cancellation_hours,
            'few_left' => $this->resource->hasFewNightsLeft(),
            'breakfast_included' => $this->resource->includesBreakfast(),
            'badges' => array_values(array_filter([
                $this->building->taxes_included ? 'tax_included' : null,
                $this->building->free_cancellation_hours !== null ? 'free_cancellation' : null,
                $this->resource->hasFewNightsLeft() ? 'few_left' : null,
                $this->resource->includesBreakfast() ? 'breakfast_included' : null,
            ])),
        ];
    }
}
