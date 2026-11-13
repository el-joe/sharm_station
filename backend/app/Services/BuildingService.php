<?php

namespace App\Services;

use App\DTOs\Building\BuildingData;
use App\Exceptions\DomainValidationException;
use App\Exceptions\GoogleReviewSyncException;
use App\Models\Building;
use App\Repositories\Contracts\BuildingRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class BuildingService
{
    public function __construct(
        private readonly BuildingRepositoryInterface $buildings,
        private readonly GoogleReviewService $reviews,
    ) {}

    public function findPublishedBySlug(string $slug): ?Building
    {
        return $this->buildings->findPublishedBySlug($slug);
    }

    /**
     * @return Builder<Building>
     */
    public function adminQuery(?int $destinationId = null): Builder
    {
        return $this->buildings->adminQuery($destinationId);
    }

    /**
     * @return array<int, string>
     */
    public function options(): array
    {
        return $this->buildings->selectOptions();
    }

    public function create(BuildingData $data): Building
    {
        return $this->buildings->create($data->toAttributes());
    }

    public function update(Building $building, BuildingData $data): Building
    {
        return $this->buildings->update($building, $data->toAttributes());
    }

    public function delete(Building $building): void
    {
        $this->buildings->delete($building);
    }

    /**
     * Refreshes the cached Google reviews of the building's own Place ID (SRS 13.4).
     *
     * @return int Number of reviews received.
     *
     * @throws DomainValidationException when no Place ID is set or Google cannot be reached.
     */
    public function syncReviews(Building $building): int
    {
        if (blank($building->google_place_id)) {
            throw DomainValidationException::withErrors(['google_place_id' => ['Save a Google Place ID for this building first.']]);
        }

        try {
            return $this->reviews->sync($building->google_place_id);
        } catch (GoogleReviewSyncException $exception) {
            throw DomainValidationException::withErrors(['google_place_id' => [$exception->getMessage()]]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function placeOptions(): array
    {
        return $this->buildings->placeOptions();
    }
}
