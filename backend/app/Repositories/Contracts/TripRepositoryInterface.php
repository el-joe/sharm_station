<?php

namespace App\Repositories\Contracts;

use App\DTOs\Ai\CatalogueQueryData;
use App\DTOs\Trip\TripAdminFilterData;
use App\DTOs\Trip\TripFilterData;
use App\Enums\TripType;
use App\Models\Trip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<Trip>
 */
interface TripRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Price bounds of $filters must already be in EUR.
     */
    public function paginatePublished(TripFilterData $filters): LengthAwarePaginator;

    public function findPublishedBySlug(string $slug): ?Trip;

    /**
     * @param  list<TripType>  $types
     * @return Collection<int, Trip>
     */
    public function featured(array $types, int $limit = 6): Collection;

    /**
     * @return Builder<Trip>
     */
    public function adminQuery(TripAdminFilterData $filters): Builder;

    public function findForEditor(int $id): Trip;

    public function isUsedInPackages(Trip $trip): bool;

    /**
     * Published trips of the given types matching every token on title or destination name.
     *
     * @param  list<string>  $tokens
     * @param  list<TripType>  $types
     * @return Collection<int, Trip>
     */
    public function suggest(array $tokens, array $types, int $limit): Collection;

    /**
     * Published trips of the given types for the partner catalogue, with everything pricing needs.
     *
     * @param  list<string>  $types
     * @param  list<string>  $tokens
     * @return Collection<int, Trip>
     */
    public function forCatalogue(array $types, ?string $destination, array $tokens): Collection;

    /**
     * Trips of every status for the MCP read tools, with destination and price relations.
     */
    public function paginateForMcp(CatalogueQueryData $query): LengthAwarePaginator;

    /**
     * One trip with everything get_trip returns (content, itinerary, options, tiers, prices).
     */
    public function findForMcp(int $id): ?Trip;
}
