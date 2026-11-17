<?php

namespace App\Services;

use App\DTOs\Ai\CatalogueQueryData;
use App\Models\Building;
use App\Models\Destination;
use App\Models\Package;
use App\Models\PackageIncludedTransfer;
use App\Models\PackageStage;
use App\Models\Room;
use App\Models\TransferFixedRoute;
use App\Models\TransferHub;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripPrivateTier;
use App\Models\TripTimelineItem;
use App\Models\TripUnitOption;
use App\Models\VehicleType;
use App\Repositories\Contracts\BuildingRepositoryInterface;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Contracts\TransferHubRepositoryInterface;
use App\Repositories\Contracts\TripRepositoryInterface;
use App\Repositories\Contracts\VehicleTypeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

/**
 * Read model of the Claude MCP server (CR-14): compact catalogue data for trips, packages, transfers and
 * accommodations, including prices per nationality group and season and SEO.
 *
 * Only product data is returned: never customers, bookings, payments, partners or partner prices.
 */
class McpCatalogueService
{
    public function __construct(
        private readonly TripRepositoryInterface $trips,
        private readonly PackageRepositoryInterface $packages,
        private readonly BuildingRepositoryInterface $buildings,
        private readonly TransferHubRepositoryInterface $hubs,
        private readonly VehicleTypeRepositoryInterface $vehicles,
        private readonly PriceMatrixService $priceMatrix,
        private readonly SeoService $seo,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function listTrips(CatalogueQueryData $query): array
    {
        return $this->page($this->trips->paginateForMcp($query), fn (Trip $trip): array => [
            'id' => $trip->id,
            'slug' => $trip->slug,
            'type' => $trip->type->value,
            'status' => $trip->status->value,
            'title' => $trip->translate('title', $query->locale),
            'destination' => $this->destination($trip->destination, $query->locale),
            'duration' => $this->duration($trip),
            'pricing_mode' => $trip->pricing_mode->value,
            'from_price_eur' => $this->number($trip->from_price),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trip(int $id, string $locale): ?array
    {
        $trip = $this->trips->findForMcp($id);

        if ($trip === null) {
            return null;
        }

        return [
            'id' => $trip->id,
            'slug' => $trip->slug,
            'type' => $trip->type->value,
            'status' => $trip->status->value,
            'title' => $trip->translate('title', $locale),
            'summary' => $trip->translate('summary', $locale),
            'description' => $trip->translate('description', $locale),
            'destination' => $this->destination($trip->destination, $locale),
            'duration' => $this->duration($trip),
            'highlights' => $trip->translate('highlights', $locale),
            'included' => $trip->translate('included', $locale),
            'excluded' => $trip->translate('excluded', $locale),
            'meeting_point' => $trip->translate('meeting_point', $locale),
            'what_to_bring' => $trip->translate('what_to_bring', $locale),
            'faq' => $trip->translate('faq', $locale),
            'cancellation_policy' => $trip->translate('cancellation_policy', $locale),
            'guide_languages' => $trip->guide_languages,
            'nile' => $trip->type->value === 'nile' ? [
                'vessel_name' => $trip->vessel_name,
                'itinerary_from' => $trip->itinerary_from,
                'itinerary_to' => $trip->itinerary_to,
                'vessel_facilities' => $trip->vessel_facilities,
                'meal_type' => $trip->meal_type,
            ] : null,
            'booking' => [
                'booking_types' => array_map(fn ($type): string => $type->value, $trip->bookingTypes()),
                'pricing_mode' => $trip->pricing_mode->value,
                'min_persons' => $trip->min_persons,
                'max_persons' => $trip->max_persons,
                'deposit_percent' => $this->number($trip->deposit_percent),
            ],
            'unit_options' => $trip->unitOptions->map(fn (TripUnitOption $option): array => [
                'id' => $option->id,
                'name' => $option->translate('name', $locale),
                'seats' => $option->seats,
                'price_eur' => $this->number($option->price),
                'max_quantity' => $option->max_quantity,
                'children_allowed' => (bool) $option->children_allowed,
            ])->values()->all(),
            'private_tiers' => $trip->privateTiers->map(fn (TripPrivateTier $tier): array => [
                'id' => $tier->id,
                'min_people' => $tier->min_people,
                'max_people' => $tier->max_people,
                'price_per_person_eur' => $this->number($tier->price_per_person),
            ])->values()->all(),
            'itinerary' => $trip->tripDays->map(fn (TripDay $day): array => [
                'day' => $day->day_number,
                'title' => $day->translate('title', $locale),
                'description' => $day->translate('description', $locale),
                'items' => $day->items->map(fn (TripTimelineItem $item): array => [
                    'from' => $item->time_from,
                    'to' => $item->time_to,
                    'title' => $item->translate('title', $locale),
                    'place' => $item->place,
                ])->values()->all(),
            ])->values()->all(),
            'price_matrix' => $this->priceMatrix->summary($trip),
            'seo' => $this->seo->current($trip)->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listPackages(CatalogueQueryData $query): array
    {
        return $this->page($this->packages->paginateForMcp($query), fn (Package $package): array => [
            'id' => $package->id,
            'slug' => $package->slug,
            'status' => $package->status->value,
            'title' => $package->translate('title', $query->locale),
            'destination' => $this->destination($package->destination, $query->locale),
            'days' => $package->days,
            'nights' => $package->nights,
            'stages' => $package->stages->map(fn (PackageStage $stage): ?string => $stage->destination?->translate('name', $query->locale))->filter()->values()->all(),
            'from_price_eur' => $this->number($package->from_price),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function package(int $id, string $locale): ?array
    {
        $package = $this->packages->findForMcp($id);

        if ($package === null) {
            return null;
        }

        return [
            'id' => $package->id,
            'slug' => $package->slug,
            'status' => $package->status->value,
            'title' => $package->translate('title', $locale),
            'summary' => $package->translate('summary', $locale),
            'description' => $package->translate('description', $locale),
            'destination' => $this->destination($package->destination, $locale),
            'days' => $package->days,
            'nights' => $package->nights,
            'highlights' => $package->translate('highlights', $locale),
            'included' => $package->translate('included', $locale),
            'excluded' => $package->translate('excluded', $locale),
            'what_to_bring' => $package->translate('what_to_bring', $locale),
            'faq' => $package->translate('faq', $locale),
            'cancellation_policy' => $package->translate('cancellation_policy', $locale),
            'price_mode' => $package->price_mode?->value,
            'transfers_included' => (bool) $package->transfers_included,
            'stages' => $package->stages->map(fn (PackageStage $stage): array => [
                'position' => $stage->position,
                'destination' => $this->destination($stage->destination, $locale),
                'days' => $stage->days,
                'nights' => $stage->nights,
                'accommodation' => $stage->translate('accommodation', $locale),
                'trips' => $stage->stageTrips->map(fn ($stageTrip): ?array => $stageTrip->trip ? [
                    'id' => $stageTrip->trip->id,
                    'slug' => $stageTrip->trip->slug,
                    'title' => $stageTrip->trip->translate('title', $locale),
                ] : null)->filter()->values()->all(),
            ])->values()->all(),
            'included_transfers' => $package->includedTransfers->map(fn (PackageIncludedTransfer $transfer): array => [
                'day' => $transfer->day_number,
                'from' => $transfer->from_place,
                'to' => $transfer->to_place,
                'vehicle_type' => $transfer->vehicle_type,
            ])->values()->all(),
            'price_matrix' => $this->priceMatrix->summary($package),
            'seo' => $this->seo->current($package)->toArray(),
        ];
    }

    /**
     * Hubs with their distance pricing and fixed routes (group prices, seasons, SEO), plus vehicle types.
     *
     * @return array<string, mixed>
     */
    public function transfers(string $locale): array
    {
        return [
            'hubs' => $this->hubs->forMcp()->map(fn (TransferHub $hub): array => [
                'id' => $hub->id,
                'slug' => $hub->slug,
                'name' => $hub->translate('name', $locale),
                'type' => $hub->type?->value,
                'enabled' => $hub->is_enabled,
                'destination' => $this->destination($hub->destination, $locale),
                'distance_pricing_eur' => [
                    'base_fare' => $this->number($hub->base_fare),
                    'distance_tiers' => $hub->distance_tiers,
                    'min_fare' => $this->number($hub->min_fare),
                    'night_surcharge' => $this->number($hub->night_surcharge),
                    'fixed_fees' => $this->number($hub->fixed_fees),
                    'return_discount_percent' => $this->number($hub->return_discount_percent),
                    'max_distance_km' => $hub->max_distance_km,
                    'min_notice_hours' => $hub->min_notice_hours,
                    'extras' => $hub->extras,
                ],
                'fixed_routes' => $hub->fixedRoutes->map(fn (TransferFixedRoute $route): array => [
                    'id' => $route->id,
                    'destination_name' => $route->translate('destination_name', $locale),
                    'vehicle_type' => $route->vehicleType?->translate('name', $locale) ?? 'Any vehicle (price × vehicle multiplier)',
                    'enabled' => $route->is_enabled,
                    'price_matrix' => $this->priceMatrix->summary($route),
                    'seo' => $this->seo->current($route)->toArray(),
                ])->values()->all(),
            ])->values()->all(),
            'vehicles' => $this->vehicles->enabled()->map(fn (VehicleType $vehicle): array => [
                'id' => $vehicle->id,
                'name' => $vehicle->translate('name', $locale),
                'category' => $vehicle->category?->value,
                'capacity' => $vehicle->capacity,
                'luggage' => $vehicle->luggage,
                'multiplier' => $this->number($vehicle->multiplier),
                'min_fare_eur' => $this->number($vehicle->min_fare),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listAccommodations(CatalogueQueryData $query): array
    {
        return $this->page($this->buildings->paginateForMcp($query), fn (Building $building): array => [
            'id' => $building->id,
            'slug' => $building->slug,
            'status' => $building->status->value,
            'name' => $building->translate('name', $query->locale),
            'type' => $building->type->value,
            'stars' => $building->stars,
            'destination' => $this->destination($building->destination, $query->locale),
            'rooms' => $building->rooms->map(fn (Room $room): array => [
                'id' => $room->id,
                'name' => $room->translate('name', $query->locale),
                'room_type' => $room->room_type->value,
                'max_total' => $room->max_total,
                'base_price_per_night_eur' => $this->number($room->base_price),
                'status' => $room->status->value,
            ])->values()->all(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function accommodation(int $id, string $locale): ?array
    {
        $building = $this->buildings->findForMcp($id);

        if ($building === null) {
            return null;
        }

        return [
            'id' => $building->id,
            'slug' => $building->slug,
            'status' => $building->status->value,
            'name' => $building->translate('name', $locale),
            'type' => $building->type->value,
            'stars' => $building->stars,
            'destination' => $this->destination($building->destination, $locale),
            'address' => $building->address,
            'lat' => $this->number($building->lat),
            'lng' => $this->number($building->lng),
            'description' => $building->translate('description', $locale),
            'amenities' => $building->amenities ?? [],
            'check_in_from' => $building->check_in_from,
            'check_out_until' => $building->check_out_until,
            'taxes_included' => $building->taxes_included,
            'tax_per_night_eur' => $this->number($building->tax_per_night),
            'cancellation_policy' => $building->translate('cancellation_policy', $locale),
            'seo' => $this->seo->current($building)->toArray(),
            'rooms' => $building->rooms->map(fn (Room $room): array => [
                'id' => $room->id,
                'name' => $room->translate('name', $locale),
                'number' => $room->number,
                'room_type' => $room->room_type->value,
                'status' => $room->status->value,
                'max_adults' => $room->max_adults,
                'max_children' => $room->max_children,
                'max_total' => $room->max_total,
                'beds' => $room->translate('beds', $locale),
                'view' => $room->translate('view', $locale),
                'size_m2' => $room->size_m2,
                'description' => $room->translate('description', $locale),
                'amenities' => $room->amenities ?? [],
                'pricing_mode' => $room->pricing_mode->value,
                'min_nights' => $room->min_nights,
                'max_nights' => $room->max_nights,
                'price_matrix' => $this->priceMatrix->summary($room),
                'seo' => $this->seo->current($room)->toArray(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function page(LengthAwarePaginator $paginator, callable $map): array
    {
        return [
            'page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'items' => collect($paginator->items())->map(fn (Model $model): array => $map($model))->values()->all(),
        ];
    }

    /**
     * @return array{slug: string, name: string}|null
     */
    private function destination(?Destination $destination, string $locale): ?array
    {
        return $destination ? ['slug' => $destination->slug, 'name' => (string) $destination->translate('name', $locale)] : null;
    }

    /**
     * @return array<string, int|float|null>
     */
    private function duration(Trip $trip): array
    {
        return array_filter([
            'hours' => $this->number($trip->duration_hours),
            'days' => $trip->days,
            'nights' => $trip->nights,
        ], fn ($value): bool => $value !== null);
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
