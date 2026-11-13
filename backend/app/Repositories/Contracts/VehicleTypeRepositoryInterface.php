<?php

namespace App\Repositories\Contracts;

use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<VehicleType>
 */
interface VehicleTypeRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return Collection<int, VehicleType>
     */
    public function enabled(): Collection;

    public function findEnabled(int $id): ?VehicleType;
}
