<?php

namespace App\Repositories\Contracts;

use App\Models\RoomOption;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends BaseRepositoryInterface<RoomOption>
 */
interface RoomOptionRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Optional rooms of a trip or package, in display order (stage, sort).
     *
     * @return Collection<int, RoomOption>
     */
    public function forOwner(Model $owner): Collection;
}
