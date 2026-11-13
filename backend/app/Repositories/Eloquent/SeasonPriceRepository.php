<?php

namespace App\Repositories\Eloquent;

use App\Models\SeasonPrice;
use App\Repositories\Contracts\SeasonPriceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends BaseRepository<SeasonPrice>
 */
class SeasonPriceRepository extends BaseRepository implements SeasonPriceRepositoryInterface
{
    protected function model(): string
    {
        return SeasonPrice::class;
    }

    public function activeFor(Model $priceable, ?int $exceptId = null): Collection
    {
        return $this->query()
            ->whereMorphedTo('priceable', $priceable)
            ->where('is_active', true)
            ->when($exceptId, fn ($query, int $id) => $query->whereKeyNot($id))
            ->orderBy('starts_on')
            ->get();
    }

    public function allFor(Model $priceable): Collection
    {
        return $this->query()
            ->whereMorphedTo('priceable', $priceable)
            ->orderBy('starts_on')
            ->get();
    }
}
