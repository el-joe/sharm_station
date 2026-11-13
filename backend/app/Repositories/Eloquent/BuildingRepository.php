<?php

namespace App\Repositories\Eloquent;

use App\Enums\RoomStatus;
use App\Models\Building;
use App\Repositories\Contracts\BuildingRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<Building>
 */
class BuildingRepository extends BaseRepository implements BuildingRepositoryInterface
{
    protected function model(): string
    {
        return Building::class;
    }

    public function findPublishedBySlug(string $slug): ?Building
    {
        return $this->query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'destination',
                'childPolicy.bands',
                'rooms' => fn ($query) => $query->where('status', RoomStatus::Active)->with(['seasonPrices' => fn ($seasons) => $seasons->where('is_active', true)]),
            ])
            ->first();
    }

    public function adminQuery(?int $destinationId = null): Builder
    {
        return $this->query()
            ->select('buildings.*')
            ->with('destination:id,name')
            ->withCount('rooms')
            ->when($destinationId, fn (Builder $query, int $id) => $query->where('destination_id', $id));
    }

    public function placeOptions(): array
    {
        return $this->query()
            ->whereNotNull('google_place_id')
            ->where('google_place_id', '!=', '')
            ->get(['id', 'name', 'google_place_id'])
            ->mapWithKeys(fn (Building $building): array => [$building->google_place_id => (string) $building->name])
            ->all();
    }
}
