<?php

namespace App\Repositories\Contracts;

use App\Models\Addon;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepositoryInterface<Addon>
 */
interface AddonRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return Builder<Addon>
     */
    public function adminQuery(): Builder;
}
