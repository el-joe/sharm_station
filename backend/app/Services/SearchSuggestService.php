<?php

namespace App\Services;

use App\DTOs\Search\SuggestQueryData;
use App\Enums\SearchScope;
use App\Enums\TripType;
use App\Models\Building;
use App\Models\Destination;
use App\Models\Package;
use App\Models\TransferFixedRoute;
use App\Models\TransferHub;
use App\Models\Trip;
use App\Repositories\Contracts\BuildingRepositoryInterface;
use App\Repositories\Contracts\DestinationRepositoryInterface;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Contracts\TransferHubRepositoryInterface;
use App\Repositories\Contracts\TripRepositoryInterface;

/**
 * Type-ahead suggestions for the search fields (hero tabs, transfer forms).
 *
 * Every word of the query must match the name or the location (so "hurghada sta" finds
 * "Star Apartment Hurghada"). Results are grouped; each item has a one-line location
 * ("Naama Bay, Sharm El Sheikh, Egypt") for the second row of the dropdown.
 */
class SearchSuggestService
{
    public function __construct(
        private readonly DestinationRepositoryInterface $destinations,
        private readonly BuildingRepositoryInterface $buildings,
        private readonly TripRepositoryInterface $trips,
        private readonly PackageRepositoryInterface $packages,
        private readonly TransferHubRepositoryInterface $hubs,
    ) {}

    /**
     * @return list<array{key: string, items: list<array<string, mixed>>}>
     */
    public function suggest(SuggestQueryData $query): array
    {
        $groups = match ($query->scope) {
            SearchScope::Trips => $this->productScope($query, [TripType::SingleDay, TripType::MultiDay]),
            SearchScope::Nile => $this->productScope($query, [TripType::Nile]),
            SearchScope::Packages => $this->packageScope($query),
            SearchScope::Stays => [
                $this->group('destinations', $this->destinationItems($query)),
                $this->group('hotels', $this->hotelItems($query->tokens, $query->limit)),
            ],
            SearchScope::TransferFrom => [
                $this->group('hubs', $this->hubs->suggest($query->tokens, $query->limit)->map(fn (TransferHub $hub): array => $this->hubItem($hub))->all()),
            ],
            SearchScope::TransferTo => $this->transferToScope($query),
        };

        return array_values(array_filter($groups, fn (array $group): bool => $group['items'] !== []));
    }

    /**
     * @param  list<TripType>  $types
     * @return list<array{key: string, items: list<array<string, mixed>>}>
     */
    private function productScope(SuggestQueryData $query, array $types): array
    {
        $products = $query->tokens === []
            ? []
            : $this->trips->suggest($query->tokens, $types, $query->limit)->map(fn (Trip $trip): array => [
                'type' => 'trip',
                'value' => $trip->slug,
                'label' => $trip->title,
                'location' => $this->location([$trip->destination?->name]),
                'meta' => ['trip_type' => $trip->type->value],
            ])->all();

        return [
            $this->group('destinations', $this->destinationItems($query)),
            $this->group($types === [TripType::Nile] ? 'nile_trips' : 'trips', $products),
        ];
    }

    /**
     * @return list<array{key: string, items: list<array<string, mixed>>}>
     */
    private function packageScope(SuggestQueryData $query): array
    {
        $packages = $query->tokens === []
            ? []
            : $this->packages->suggest($query->tokens, $query->limit)->map(fn (Package $package): array => [
                'type' => 'package',
                'value' => $package->slug,
                'label' => $package->title,
                'location' => $this->location([$package->destination?->name]),
                'meta' => [],
            ])->all();

        return [
            $this->group('destinations', $this->destinationItems($query)),
            $this->group('packages', $packages),
        ];
    }

    /**
     * Drop-off: the hub's fixed-price places first, then hotels in the hub's destination
     * (both carry coordinates, so the transfer quote can be priced).
     *
     * @return list<array{key: string, items: list<array<string, mixed>>}>
     */
    private function transferToScope(SuggestQueryData $query): array
    {
        $hub = $query->hubId !== null ? $this->hubs->findEnabled($query->hubId) : null;
        if ($hub === null) {
            return [];
        }
        $hub->loadMissing('destination');
        $city = $hub->destination?->name;

        $places = $this->hubs->suggestPlaces($hub->id, $query->tokens, $query->limit)
            ->map(fn (TransferFixedRoute $route): array => [
                'type' => 'place',
                'value' => $route->destination_name,
                'label' => $route->destination_name,
                'location' => $this->location([$city]),
                'meta' => ['lat' => $route->lat, 'lng' => $route->lng, 'fixed_price' => true],
            ])->all();

        $hotels = $this->hotelItems($query->tokens, $query->limit, $hub->destination_id);

        return [
            $this->group('places', $places),
            $this->group('hotels', $hotels),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function destinationItems(SuggestQueryData $query): array
    {
        return $this->destinations->suggest($query->tokens, $query->limit)->map(fn (Destination $destination): array => [
            'type' => 'destination',
            'value' => $destination->slug,
            'label' => $destination->name,
            'location' => $this->location([]),
            'meta' => ['trips_count' => (int) ($destination->trips_count ?? 0)],
        ])->all();
    }

    /**
     * @param  list<string>  $tokens
     * @return list<array<string, mixed>>
     */
    private function hotelItems(array $tokens, int $limit, ?int $destinationId = null): array
    {
        return $this->buildings->suggest($tokens, $limit, $destinationId)->map(fn (Building $building): array => [
            'type' => 'hotel',
            'value' => $building->slug,
            'label' => $building->name,
            'location' => $this->location([$building->address, $building->destination?->name]),
            'meta' => ['stars' => $building->stars, 'lat' => $building->lat !== null ? (float) $building->lat : null, 'lng' => $building->lng !== null ? (float) $building->lng : null],
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function hubItem(TransferHub $hub): array
    {
        return [
            'type' => 'hub',
            'value' => (string) $hub->id,
            'label' => $hub->name,
            'location' => $this->location([$hub->destination?->name]),
            'meta' => ['hub_type' => $hub->type?->value],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{key: string, items: list<array<string, mixed>>}
     */
    private function group(string $key, array $items): array
    {
        return ['key' => $key, 'items' => array_values($items)];
    }

    /**
     * "Address, City, Egypt" without empty or repeated parts.
     *
     * @param  list<string|null>  $parts
     */
    private function location(array $parts): string
    {
        $parts[] = __('catalog.country');
        $clean = [];
        // Addresses often already contain the city and country ("Corniche El Nile, Luxor, Egypt"),
        // so compare comma-separated pieces, not whole parts.
        $pieces = array_merge(...array_map(fn (?string $part): array => explode(',', (string) $part), $parts));
        foreach ($pieces as $part) {
            $part = trim($part);
            if ($part !== '' && ! in_array(mb_strtolower($part), array_map('mb_strtolower', $clean), true)) {
                $clean[] = $part;
            }
        }

        return implode(', ', $clean);
    }
}
