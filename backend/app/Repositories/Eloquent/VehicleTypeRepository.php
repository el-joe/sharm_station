<?php

namespace App\Repositories\Eloquent;

use App\Models\VehicleType;
use App\Repositories\Contracts\VehicleTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<VehicleType>
 */
class VehicleTypeRepository extends BaseRepository implements VehicleTypeRepositoryInterface
{
    protected function model(): string
    {
        return VehicleType::class;
    }

    public function enabled(): Collection
    {
        return $this->query()->enabled()->orderBy('sort')->orderBy('capacity')->get();
    }

    public function findEnabled(int $id): ?VehicleType
    {
        return $this->query()->enabled()->find($id);
    }
}
