<?php

namespace App\Http\Resources\V1;

use App\Enums\BookingType;
use App\Enums\PricingMode;
use App\Enums\TripType;
use App\Http\Resources\V1\Concerns\PresentsContent;
use App\Http\Resources\V1\Concerns\PresentsPrices;
use App\Models\Addon;
use App\Models\ChildAgeBand;
use App\Models\Trip;
use App\Models\TripAvailabilityRule;
use App\Models\TripDay;
use App\Models\TripMedia;
use App\Models\TripPrivateTier;
use App\Models\TripTimelineItem;
use App\Models\TripUnitOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Trip detail with the customer prices of the request's nationality group (never partner prices).
 *
 * @mixin Trip
 */
class TripResource extends JsonResource
{
    use PresentsContent, PresentsPrices;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new TripCardResource($this->resource))->toArray($request),
            'description' => $this->description,
            'highlights' => $this->highlights ?? [],
            'included' => $this->included ?? [],
            'excluded' => $this->excluded ?? [],
            'meeting_point' => $this->meeting_point,
            'location' => $this->lat !== null ? ['lat' => (float) $this->lat, 'lng' => (float) $this->lng] : null,
            'deposit_percent' => (float) $this->deposit_percent,
            'group_prices' => $this->group_enabled && $this->pricing_mode === PricingMode::PerPerson ? [
                'adult' => $this->groupBasePrice($this->resource, 'adult_price'),
                'child' => $this->groupBasePrice($this->resource, 'child_price'),
            ] : null,
            'private_tiers' => $this->privateTiers->map(fn (TripPrivateTier $tier): array => [
                'id' => $tier->id,
                'label' => $tier->label(),
                'min_people' => $tier->min_people,
                'max_people' => $tier->max_people,
                'price_per_person' => $this->groupBasePrice($this->resource, "tier_prices.{$tier->id}"),
            ])->values(),
            'unit_options' => $this->unitOptions->map(fn (TripUnitOption $option): array => [
                'id' => $option->id,
                'name' => $option->name,
                'seats' => $option->seats,
                'price' => $this->groupBasePrice($this->resource, "unit_prices.{$option->id}"),
                'max_quantity' => $option->max_quantity,
                'children_allowed' => $option->children_allowed,
            ])->values(),
            'price_list' => $this->priceList($this->resource),
            'child_policy' => $this->childPolicy ? [
                'children_allowed' => $this->childPolicy->children_allowed,
                'adult_from_age' => $this->childPolicy->adult_from_age,
                'bands' => $this->childPolicy->bands->map(fn (ChildAgeBand $band): array => [
                    'min_age' => $band->min_age,
                    'max_age' => $band->max_age,
                    'discount_type' => $band->discount_type->value,
                    'discount_value' => $band->discount_value !== null ? (float) $band->discount_value : null,
                    'label' => $band->label(),
                ])->values(),
            ] : null,
            'availability' => $this->availabilityPayload(),
            'itinerary' => $this->tripDays->map(fn (TripDay $day): array => [
                'day_number' => $day->day_number,
                'title' => $day->title,
                'description' => $day->description,
                'items' => $day->items->map(fn (TripTimelineItem $item): array => [
                    'time_from' => $item->time_from ? substr((string) $item->time_from, 0, 5) : null,
                    'time_to' => $item->time_to ? substr((string) $item->time_to, 0, 5) : null,
                    'title' => $item->title,
                    'description' => $item->description,
                    'place' => $item->place,
                    'image' => $item->image,
                    'transfer_included' => (bool) $item->transfer_included,
                ])->values(),
            ])->values(),
            'media' => $this->media->map(fn (TripMedia $media): array => ['path' => $media->path, 'alt' => $media->alt])->values(),
            'addons' => $this->addons->map(fn (Addon $addon): array => [
                'id' => $addon->id,
                'name' => $addon->name,
                'description' => $addon->description,
                'image' => $addon->image,
                'price' => $this->money($addon->price),
                'pricing_basis' => $addon->pricing_basis->value,
                'required' => $addon->required,
                'max_quantity' => $addon->max_quantity,
                'booking_types' => $addon->booking_types ?? [],
            ])->values(),
            'nile' => $this->when($this->type === TripType::Nile, fn (): array => [
                'vessel_name' => $this->vessel_name,
                'itinerary_from' => $this->itinerary_from,
                'itinerary_to' => $this->itinerary_to,
                'vessel_facilities' => $this->vessel_facilities ?? [],
                'meal_type' => $this->meal_type,
                'facility_labels' => $this->catalogLabels('facilities', $this->vessel_facilities),
                'meal_type_label' => $this->catalogLabel('meal_types', $this->meal_type),
                'cabins' => array_values(array_map(fn (array $cabin): array => [
                    'type' => (string) ($cabin['type'] ?? 'standard'),
                    'label' => is_array($cabin['label'] ?? null)
                        ? ($cabin['label'][app()->getLocale()] ?? $cabin['label']['en'] ?? reset($cabin['label']))
                        : ($cabin['label'] ?? $this->catalogLabel('cabin_types', $cabin['type'] ?? 'standard')),
                    'capacity' => (int) ($cabin['capacity'] ?? 2),
                    'stars' => isset($cabin['stars']) ? (int) $cabin['stars'] : null,
                ], $this->cabins ?? [])),
            ]),
            ...$this->practicalInfo($this->resource),
            'seo' => $this->seo,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function availabilityPayload(): array
    {
        $payload = [];

        foreach (BookingType::cases() as $type) {
            /** @var TripAvailabilityRule|null $rule */
            $rule = $this->availabilityRules->first(fn (TripAvailabilityRule $rule): bool => $rule->booking_type === $type);

            $payload[$type->value] = [
                'enabled' => $this->supportsBookingType($type),
                'lead_time_hours' => $rule?->lead_time_hours ?? 24,
                'weekdays' => $type === BookingType::Group ? array_map('intval', $rule?->weekdays ?? []) : null,
                'dates' => $type === BookingType::Group ? ($rule?->dates ?? []) : null,
                'blackout_dates' => $rule?->blackout_dates ?? [],
            ];
        }

        return $payload;
    }
}
