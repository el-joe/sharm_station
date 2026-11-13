<?php

namespace App\Repositories\Eloquent;

use App\Models\Addon;
use App\Repositories\Contracts\AddonRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<Addon>
 */
class AddonRepository extends BaseRepository implements AddonRepositoryInterface
{
    protected function model(): string
    {
        return Addon::class;
    }

    public function adminQuery(): Builder
    {
        return $this->query()->select('addons.*')->withCount('trips');
    }
}
