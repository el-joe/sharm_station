<?php

namespace App\Models;

use App\Models\Concerns\HasPriceMaps;
use Database\Factories\ProductPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Base price of a trip, package, room or transfer fixed route for one nationality group.
 */
#[Fillable(['priceable_type', 'priceable_id', 'nationality_group_id', 'adult_price', 'child_price', 'tier_prices', 'unit_prices', 'price_per_night', 'price'])]
class ProductPrice extends Model
{
    /** @use HasFactory<ProductPriceFactory> */
    use HasFactory, HasPriceMaps;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'adult_price' => 'decimal:2',
            'child_price' => 'decimal:2',
            'price_per_night' => 'decimal:2',
            'price' => 'decimal:2',
            'unit_prices' => 'array',
            'tier_prices' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function priceable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<NationalityGroup, $this>
     */
    public function nationalityGroup(): BelongsTo
    {
        return $this->belongsTo(NationalityGroup::class);
    }
}
