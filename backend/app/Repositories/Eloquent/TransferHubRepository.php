<?php

namespace App\Repositories\Eloquent;

use App\Models\TransferFixedRoute;
use App\Models\TransferHub;
use App\Repositories\Contracts\TransferHubRepositoryInterface;
use App\Repositories\Eloquent\Concerns\MatchesSearchTokens;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<TransferHub>
 */
class TransferHubRepository extends BaseRepository implements TransferHubRepositoryInterface
{
    use MatchesSearchTokens;

    protected function model(): string
    {
        return TransferHub::class;
    }

    public function enabled(): Collection
    {
        return $this->query()->enabled()->with(['destination', 'fixedRoutes' => fn ($query) => $query->where('is_enabled', true)])->orderBy('sort')->get();
    }

    public function findEnabled(int $id): ?TransferHub
    {
        return $this->query()->enabled()->find($id);
    }

    public function suggest(array $tokens, int $limit): Collection
    {
        $query = $this->query()->enabled()->with('destination');
        $this->matchTokens($query, $tokens, ['name', 'destination.name']);

        return $query->orderBy('sort')->limit($limit)->get();
    }

    public function suggestPlaces(int $hubId, array $tokens, int $limit): Collection
    {
        $query = TransferFixedRoute::query()->where('transfer_hub_id', $hubId)->where('is_enabled', true);
        $this->matchTokens($query, $tokens, ['destination_name']);

        return $query->orderBy('destination_name')->limit($limit * 2)->get()
            ->unique(fn (TransferFixedRoute $route): string => $route->lat.','.$route->lng)
            ->take($limit)
            ->values();
    }

    public function forMcp(): Collection
    {
        return $this->query()
            ->with(['destination:id,name,slug', 'fixedRoutes.vehicleType', 'fixedRoutes.seasonPrices', 'fixedRoutes.productPrices'])
            ->orderBy('sort')
            ->get();
    }
}
