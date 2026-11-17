<?php

namespace App\Models\Concerns;

use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Base price per nationality group (price matrix, CR-10).
 */
trait HasProductPrices
{
    /**
     * @return MorphMany<ProductPrice, $this>
     */
    public function productPrices(): MorphMany
    {
        return $this->morphMany(ProductPrice::class, 'priceable');
    }
}
