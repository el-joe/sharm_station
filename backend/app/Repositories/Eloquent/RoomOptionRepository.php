<?php

namespace App\Repositories\Eloquent;

use App\Models\RoomOption;
use App\Repositories\Contracts\RoomOptionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends BaseRepository<RoomOption>
 */
class RoomOptionRepository extends BaseRepository implements RoomOptionRepositoryInterface
{
    protected function model(): string
    {
        return RoomOption::class;
    }

    public function forOwner(Model $owner): Collection
    {
        return $this->query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->withCount(['seasonPrices', 'productPrices'])
            ->orderBy('stage_position')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }
}
