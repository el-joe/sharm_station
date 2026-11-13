<?php

namespace App\Repositories\Contracts;

use App\Models\SeasonPrice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends BaseRepositoryInterface<SeasonPrice>
 */
interface SeasonPriceRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return Collection<int, SeasonPrice>
     */
    public function activeFor(Model $priceable, ?int $exceptId = null): Collection;

    /**
     * @return Collection<int, SeasonPrice>
     */
    public function allFor(Model $priceable): Collection;
}
