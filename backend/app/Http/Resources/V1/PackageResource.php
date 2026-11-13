<?php

namespace App\Http\Resources\V1;

use App\Enums\BookingType;
use App\Enums\CommissionCategory;
use App\Http\Resources\V1\Concerns\PresentsContent;
use App\Http\Resources\V1\Concerns\PresentsPrices;
use App\Models\Addon;
use App\Models\ChildAgeBand;
use App\Models\Package;
use App\Models\PackageIncludedTransfer;
use App\Models\PackageStage;
use App\Models\PackageStageTrip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Package detail: stages with their days and linked trips, included transfers (never charged),
 * an overall day-by-day timeline, add-ons and the seasonal price list.
 *
 * @mixin Package
 */
class PackageResource extends JsonResource
{
    use PresentsContent, PresentsPrices;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $category = CommissionCategory::Packages;
        $isPartner = $this->partner($request) !== null;
        $startDays = $this->resource->stageStartDays();

        return [
            ...(new PackageCardResource($this->resource))->toArray($request),
            'description' => $this->description,
            'highlights' => $this->highlights ?? [],
            'included' => $this->included ?? [],
            'excluded' => $this->excluded ?? [],
            'deposit_percent' => (float) $this->deposit_percent,
            'discount_percent' => $this->discount_percent !== null ? (float) $this->discount_percent : null,
            'group_prices' => [
                'adult' => $this->money($this->base_price),
                'child' => $this->money($this->base_child_price ?? $this->base_price),
            ],
            'partner_price' => $this->when($isPartner, fn (): ?array => $this->partnerMoney($request, $category, $this->from_price ?? $this->base_price, $this->partner_price)),
            'stages' => $this->stages->map(fn (PackageStage $stage): array => [
                'position' => $stage->position,
                'destination' => $stage->destination ? ['name' => $stage->destination->name, 'slug' => $stage->destination->slug] : null,
                'days' => $stage->days,
                'nights' => $stage->nights,
                'start_day' => $startDays[$stage->id] ?? null,
                'end_day' => isset($startDays[$stage->id]) ? $startDays[$stage->id] + max(1, $stage->days) - 1 : null,
                'accommodation' => $stage->accommodation,
                'trips' => $stage->stageTrips->map(fn (PackageStageTrip $stageTrip): array => [
                    'stage_day' => $stageTrip->day_number,
                    'day_number' => ($startDays[$stage->id] ?? 1) + $stageTrip->day_number - 1,
                    'trip' => $stageTrip->trip ? $this->tripSummary($stageTrip) : null,
                ])->values(),
            ])->values(),
            'trips' => $this->stages->flatMap(fn (PackageStage $stage) => $stage->stageTrips)
                ->filter(fn (PackageStageTrip $stageTrip): bool => $stageTrip->trip !== null)
                ->unique('trip_id')
                ->map(fn (PackageStageTrip $stageTrip): array => $this->tripSummary($stageTrip))
                ->values(),
            'transfers_included' => true,
            'included_transfers' => $this->includedTransfers->map(fn (PackageIncludedTransfer $transfer): array => [
                'day_number' => $transfer->day_number,
                'time' => $transfer->time ? substr((string) $transfer->time, 0, 5) : null,
                'from' => $transfer->from_place,
                'to' => $transfer->to_place,
                'vehicle_type' => $transfer->vehicle_type?->value,
                'vehicle_label' => $this->catalogLabel('vehicles', $transfer->vehicle_type?->value),
                'stage_position' => $transfer->stage?->position,
                'transfer_included' => true,
            ])->values(),
            'itinerary' => $this->timeline($startDays),
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
                ...($isPartner ? ['partner_price' => $this->partnerMoney($request, $category, $addon->price, $addon->partner_price)] : []),
            ])->values(),
            'availability' => collect(BookingType::cases())->mapWithKeys(fn (BookingType $type): array => [
                $type->value => ['enabled' => $this->supportsBookingType($type), 'lead_time_hours' => 24],
            ])->all(),
            ...$this->practicalInfo($this->resource),
            'seo' => $this->seo,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tripSummary(PackageStageTrip $stageTrip): array
    {
        $trip = $stageTrip->trip;

        return [
            'id' => $trip->id,
            'slug' => $trip->slug,
            'type' => $trip->type->value,
            'title' => $trip->title,
            'summary' => $trip->summary,
            'cover_image' => $trip->cover_image,
            'duration_hours' => $trip->duration_hours !== null ? (float) $trip->duration_hours : null,
            'destination' => $trip->destination ? ['name' => $trip->destination->name, 'slug' => $trip->destination->slug] : null,
        ];
    }

    /**
     * One entry per package day with its trips and included transfers ("Transfer included" items).
     *
     * @param  array<int, int>  $startDays
     * @return list<array<string, mixed>>
     */
    private function timeline(array $startDays): array
    {
        $days = [];

        for ($day = 1; $day <= max(1, (int) $this->days); $day++) {
            $stage = $this->stages->last(fn (PackageStage $stage): bool => ($startDays[$stage->id] ?? PHP_INT_MAX) <= $day);
            $items = [];

            foreach ($this->includedTransfers->where('day_number', $day) as $transfer) {
                $items[] = [
                    'type' => 'transfer',
                    'time' => $transfer->time ? substr((string) $transfer->time, 0, 5) : null,
                    'title' => $transfer->from_place.' → '.$transfer->to_place,
                    'transfer_included' => true,
                ];
            }

            foreach ($stage?->stageTrips ?? [] as $stageTrip) {
                if ($stageTrip->trip !== null && ($startDays[$stage->id] + $stageTrip->day_number - 1) === $day) {
                    $items[] = [
                        'type' => 'trip',
                        'time' => null,
                        'title' => $stageTrip->trip->title,
                        'trip_slug' => $stageTrip->trip->slug,
                        'transfer_included' => false,
                    ];
                }
            }

            usort($items, fn (array $a, array $b): int => strcmp((string) ($a['time'] ?? '99'), (string) ($b['time'] ?? '99')));

            $days[] = [
                'day_number' => $day,
                'stage_position' => $stage?->position,
                'destination' => $stage?->destination?->name,
                'accommodation' => $stage?->accommodation,
                'items' => $items,
            ];
        }

        return $days;
    }
}
