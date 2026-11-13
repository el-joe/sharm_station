<?php

namespace App\Repositories\Eloquent;

use App\Models\TransferFixedRoute;
use App\Repositories\Contracts\TransferFixedRouteRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<TransferFixedRoute>
 */
class TransferFixedRouteRepository extends BaseRepository implements TransferFixedRouteRepositoryInterface
{
    protected function model(): string
    {
        return TransferFixedRoute::class;
    }

    public function forHubAndVehicle(int $hubId, int $vehicleTypeId): Collection
    {
        return $this->query()
            ->where('transfer_hub_id', $hubId)
            ->where('is_enabled', true)
            ->where(fn (Builder $query) => $query->whereNull('vehicle_type_id')->orWhere('vehicle_type_id', $vehicleTypeId))
            ->orderByRaw('vehicle_type_id is null')
            ->get();
    }

    public function adminQuery(): Builder
    {
        return $this->query()->select('transfer_fixed_routes.*')->with(['hub:id,name', 'vehicleType:id,name']);
    }
}
