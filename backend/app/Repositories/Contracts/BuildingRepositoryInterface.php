<?php

namespace App\Repositories\Contracts;

use App\Models\Building;
use Illuminate\Database\Eloquent\Builder;

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
}
