<?php

namespace App\Models\Concerns;

use App\Models\SeasonPrice;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasSeasonPrices
{
    /**
     * @return MorphMany<SeasonPrice, $this>
     */
    public function seasonPrices(): MorphMany
    {
        return $this->morphMany(SeasonPrice::class, 'priceable')->orderBy('starts_on');
    }
}
