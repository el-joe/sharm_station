<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\V1\Concerns\PresentsPrices;
use App\Models\TransferFixedRoute;
use App\Models\TransferHub;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Departure point for the transfer form. Pricing internals (tiers, fees) stay on the server.
 *
 * @mixin TransferHub
 */
class TransferHubResource extends JsonResource
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
            'name' => $this->name,
            'type' => $this->type->value,
            'destination' => $this->destination ? ['name' => $this->destination->name, 'slug' => $this->destination->slug] : null,
            'location' => ['lat' => $this->lat, 'lng' => $this->lng],
            'radius_km' => $this->radius_km,
            'max_distance_km' => $this->max_distance_km,
            'min_notice_hours' => $this->min_notice_hours,
            'return_discount_percent' => (float) $this->return_discount_percent,
            'deposit_percent' => $this->deposit_percent !== null ? (float) $this->deposit_percent : 100.0,
            'vehicle_type_ids' => $this->vehicle_type_ids !== null && $this->vehicle_type_ids !== [] ? array_map('intval', $this->vehicle_type_ids) : null,
            'extras' => array_values(array_map(fn (array $extra): array => [
                'code' => (string) $extra['code'],
                'label' => (string) ($extra['label'] ?? $extra['code']),
                'price' => $this->money((float) ($extra['price'] ?? 0)),
            ], $this->extras ?? [])),
            'places' => $this->fixedRoutes->unique(fn (TransferFixedRoute $route): string => $route->lat.','.$route->lng)
                ->map(fn (TransferFixedRoute $route): array => [
                    'name' => $route->destination_name,
                    'lat' => $route->lat,
                    'lng' => $route->lng,
                ])->values(),
        ];
    }
}
