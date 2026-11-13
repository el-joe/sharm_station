<?php

namespace App\Services;

use App\DTOs\Addon\AddonData;
use App\Models\Addon;
use App\Repositories\Contracts\AddonRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reusable add-ons library (SRS 3.5).
 */
class AddonService
{
    public function __construct(private readonly AddonRepositoryInterface $addons) {}

    /**
     * @return Builder<Addon>
     */
    public function adminQuery(): Builder
    {
        return $this->addons->adminQuery();
    }

    public function create(AddonData $data): Addon
    {
        return $this->addons->create($data->toAttributes());
    }

    public function update(Addon $addon, AddonData $data): Addon
    {
        return $this->addons->update($addon, $data->toAttributes());
    }

    /**
     * Bookings keep a copy of the add-on name and price, so deleting only detaches it from products.
     */
    public function delete(Addon $addon): void
    {
        $this->addons->delete($addon);
    }
}
