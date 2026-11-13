<?php

namespace App\Http\Resources\V1;

use App\Models\Building;
use App\Services\CurrencyService;
use App\Services\GoogleReviewService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Building data shown on room cards and destination pages.
 *
 * @mixin Building
 */
class BuildingCardResource extends JsonResource
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
            'type' => $this->type->value,
            'stars' => $this->stars,
            'address' => $this->address,
            'cover_image' => $this->cover_image,
            'destination' => $this->whenLoaded('destination', fn (): array => [
                'name' => $this->destination->name,
                'slug' => $this->destination->slug,
            ]),
            'location' => $this->lat !== null ? ['lat' => (float) $this->lat, 'lng' => (float) $this->lng] : null,
            'tax_included' => $this->taxes_included,
            'tax_per_night' => $this->taxes_included ? null : app(CurrencyService::class)->money($this->tax_per_night),
            'google' => app(GoogleReviewService::class)->rating($this->google_place_id),
        ];
    }
}
