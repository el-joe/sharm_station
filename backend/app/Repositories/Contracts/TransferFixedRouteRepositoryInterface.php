<?php

namespace App\Repositories\Contracts;

use App\Models\TransferFixedRoute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<TransferFixedRoute>
 */
interface TransferFixedRouteRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Enabled fixed routes of a hub that apply to the vehicle (specific vehicle first).
     *
     * @return Collection<int, TransferFixedRoute>
     */
    public function forHubAndVehicle(int $hubId, int $vehicleTypeId): Collection;

    /**
     * @return Builder<TransferFixedRoute>
     */
    public function adminQuery(): Builder;
}
