<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Ai\CatalogueQueryData;
use App\Enums\RoomStatus;
use App\Models\Building;
use App\Repositories\Contracts\BuildingRepositoryInterface;
use App\Repositories\Eloquent\Concerns\MatchesSearchTokens;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Building>
 */
class BuildingRepository extends BaseRepository implements BuildingRepositoryInterface
{
    use MatchesSearchTokens;

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
                'rooms' => fn ($query) => $query->where('status', RoomStatus::Active)->with(['seasonPrices' => fn ($seasons) => $seasons->where('is_active', true), 'productPrices']),
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

    public function suggest(array $tokens, int $limit, ?int $destinationId = null): Collection
    {
        $query = $this->query()->published()->with('destination');
        if ($destinationId !== null) {
            $query->where('destination_id', $destinationId);
        }
        $this->matchTokens($query, $tokens, ['name', 'address', 'destination.name']);

        return $query->orderByDesc('featured')->orderByDesc('stars')->limit($limit)->get();
    }

    public function paginateForMcp(CatalogueQueryData $query): LengthAwarePaginator
    {
        $builder = $this->query()
            ->with(['destination:id,name,slug', 'rooms'])
            ->when($query->destination, fn (Builder $builder, string $slug) => $builder->whereHas('destination', fn (Builder $destination) => $destination->where('slug', $slug)));
        $this->matchTokens($builder, $query->tokens, ['name', 'slug', 'address', 'destination.name']);

        return $builder->orderBy('id')->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    public function findForMcp(int $id): ?Building
    {
        return $this->query()
            ->with(['destination', 'rooms.seasonPrices', 'rooms.productPrices'])
            ->find($id);
    }
}
