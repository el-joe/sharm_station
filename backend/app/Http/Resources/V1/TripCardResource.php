<?php

namespace App\Http\Resources\V1;

use App\Enums\BookingType;
use App\Enums\TripType;
use App\Http\Resources\V1\Concerns\PresentsPrices;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Trip
 */
class TripCardResource extends JsonResource
{
    use PresentsPrices;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'category' => $this->category,
            'title' => $this->title,
            'summary' => $this->summary,
            'cover_image' => $this->cover_image,
            'destination' => $this->whenLoaded('destination', fn (): array => [
                'name' => $this->destination->name,
                'slug' => $this->destination->slug,
            ]),
            'duration_hours' => $this->duration_hours !== null ? (float) $this->duration_hours : null,
            'days' => $this->days,
            'nights' => $this->nights,
            'pricing_mode' => $this->pricing_mode->value,
            'booking_types' => array_map(fn (BookingType $type): string => $type->value, $this->bookingTypes()),
            'min_persons' => $this->min_persons,
            'max_persons' => $this->max_persons,
            'from_price' => $this->money($this->from_price),
            'price_unit' => $this->pricing_mode->value,
            'partner_from_price' => $this->when(
                $this->partner($request) !== null,
                fn (): ?array => $this->partnerMoney($request, $this->type->commissionCategory(), $this->from_price),
            ),
            'free_cancellation_hours' => $this->free_cancellation_hours,
            'featured' => $this->featured,
            'unit_label' => $this->unit_label,
            'rating' => $this->googleRating($request)['rating'],
            'reviews_count' => $this->googleRating($request)['reviews_count'],
            'nile' => $this->when($this->type === TripType::Nile, fn (): array => [
                'vessel_name' => $this->vessel_name,
                'itinerary_from' => $this->itinerary_from,
                'itinerary_to' => $this->itinerary_to,
            ]),
        ];
    }
}
