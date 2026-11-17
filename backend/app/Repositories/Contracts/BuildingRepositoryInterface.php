<?php

namespace App\Repositories\Contracts;

use App\DTOs\Ai\CatalogueQueryData;
use App\Models\Building;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<Building>
 */
interface BuildingRepositoryInterface extends BaseRepositoryInterface
{
    public function findPublishedBySlug(string $slug): ?Building;

    /**
     * @return Builder<Building>
     */
    public function adminQuery(?int $destinationId = null): Builder;

    /**
     * Buildings that have their own Google Place ID: place id => building name.
     *
     * @return array<string, string>
     */
    public function placeOptions(): array;

    /**
     * Published buildings matching every token on name, address or destination name.
     *
     * @param  list<string>  $tokens
     * @return Collection<int, Building>
     */
    public function suggest(array $tokens, int $limit, ?int $destinationId = null): Collection;

    /**
     * Buildings of every status with their rooms for the MCP read tools.
     */
    public function paginateForMcp(CatalogueQueryData $query): LengthAwarePaginator;

    public function findForMcp(int $id): ?Building;
}
