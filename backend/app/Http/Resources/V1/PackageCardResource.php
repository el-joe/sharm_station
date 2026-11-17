<?php

namespace App\Http\Resources\V1;

use App\Enums\BookingType;
use App\Http\Resources\V1\Concerns\PresentsPrices;
use App\Models\Package;
use App\Models\PackageStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Package
 */
class PackageCardResource extends JsonResource
{
    use PresentsPrices;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rating = $this->googleRating($request);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'cover_image' => $this->cover_image,
            'days' => $this->days,
            'nights' => $this->nights,
            'pricing_mode' => $this->pricing_mode->value,
            'min_persons' => $this->min_persons,
            'max_persons' => $this->max_persons,
            'from_price' => $this->groupFromPrice($this->resource, $this->from_price ?? $this->base_price),
            'price_unit' => 'per_person',
            'stages' => $this->whenLoaded('stages', fn () => $this->stages->map(fn (PackageStage $stage): array => [
                'destination' => $stage->destination?->name,
                'destination_slug' => $stage->destination?->slug,
                'days' => $stage->days,
                'nights' => $stage->nights,
            ])->values()),
            'destination' => $this->whenLoaded('destination', fn (): ?array => $this->destination ? [
                'name' => $this->destination->name,
                'slug' => $this->destination->slug,
            ] : null),
            'booking_types' => array_map(fn (BookingType $type): string => $type->value, $this->bookingTypes()),
            'price_mode' => $this->price_mode->value,
            'transfers_included' => true,
            'free_cancellation_hours' => $this->free_cancellation_hours,
            'featured' => $this->featured,
            'rating' => $rating['rating'],
            'reviews_count' => $rating['reviews_count'],
        ];
    }
}
