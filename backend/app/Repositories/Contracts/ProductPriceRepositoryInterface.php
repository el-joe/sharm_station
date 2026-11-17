<?php

namespace App\Repositories\Contracts;

use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends BaseRepositoryInterface<ProductPrice>
 */
interface ProductPriceRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return Collection<int, ProductPrice>
     */
    public function allFor(Model $priceable): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertFor(Model $priceable, int $groupId, array $attributes): ProductPrice;

    /**
     * @param  list<int>  $keepGroupIds
     */
    public function deleteOthers(Model $priceable, array $keepGroupIds): void;
}
