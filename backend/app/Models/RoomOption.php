<?php

namespace App\Models;

use App\Enums\RoomOptionUnitType;
use App\Enums\RoomPricePeriod;
use App\Enums\RoomPricingMode;
use App\Models\Concerns\HasProductPrices;
use App\Models\Concerns\HasSeasonPrices;
use App\Models\Concerns\HasTranslations;
use App\Models\Contracts\Priceable;
use Database\Factories\RoomOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Optional room inside a multi-day trip, Nile trip (cabin) or package stage (SRS 7.1): a priced add-on without
 * an availability calendar. Its customer price ("price" field) goes through the nationality/season price matrix.
 */
#[Fillable([
    'owner_type', 'owner_id', 'stage_position', 'unit_type', 'name', 'stars', 'pricing_mode', 'price_period', 'price',
    'max_adults', 'max_children', 'max_total', 'max_quantity', 'is_active', 'sort',
])]
class RoomOption extends Model implements Priceable
{
    /** @use HasFactory<RoomOptionFactory> */
    use HasFactory, HasProductPrices, HasSeasonPrices, HasTranslations;

    /**
     * @var list<string>
     */
    protected array $translatable = ['name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_type' => RoomOptionUnitType::class,
            'pricing_mode' => RoomPricingMode::class,
            'price_period' => RoomPricePeriod::class,
            'price' => 'decimal:2',
            'stars' => 'integer',
            'stage_position' => 'integer',
            'max_adults' => 'integer',
            'max_children' => 'integer',
            'max_total' => 'integer',
            'max_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function label(): string
    {
        return trim($this->name.($this->stars ? ' '.str_repeat('★', $this->stars) : ''));
    }

    public function basePriceFor(string $field): ?float
    {
        return $field === 'price' && $this->price !== null ? (float) $this->price : null;
    }

    public function priceFields(): array
    {
        $unit = $this->pricing_mode === RoomPricingMode::PerPerson ? 'per person' : 'per room';
        $period = $this->price_period === RoomPricePeriod::PerStay ? 'per stay' : 'per night';

        return ['price' => ucfirst($unit).' / '.$period];
    }
}
