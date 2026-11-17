<?php

namespace App\Repositories\Eloquent;

use App\Models\ProductPrice;
use App\Repositories\Contracts\ProductPriceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends BaseRepository<ProductPrice>
 */
class ProductPriceRepository extends BaseRepository implements ProductPriceRepositoryInterface
{
    protected function model(): string
    {
        return ProductPrice::class;
    }

    public function allFor(Model $priceable): Collection
    {
        return $this->query()->whereMorphedTo('priceable', $priceable)->get();
    }

    public function upsertFor(Model $priceable, int $groupId, array $attributes): ProductPrice
    {
        return $this->query()->updateOrCreate([
            'priceable_type' => $priceable->getMorphClass(),
            'priceable_id' => $priceable->getKey(),
            'nationality_group_id' => $groupId,
        ], $attributes);
    }

    public function deleteOthers(Model $priceable, array $keepGroupIds): void
    {
        $this->query()->whereMorphedTo('priceable', $priceable)->whereNotIn('nationality_group_id', $keepGroupIds)->delete();
    }
}
