<?php

namespace App\Models;

use App\Models\Concerns\HasPriceMaps;
use App\Models\Concerns\HasTranslations;
use Carbon\CarbonInterface;
use Database\Factories\SeasonPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A price period of a trip, package, room or transfer fixed route, for one nationality group
 * (nationality_group_id) or for all groups (null).
 *
 * unit_prices / tier_prices map option or tier ids to the customer price.
 */
#[Fillable([
    'priceable_type', 'priceable_id', 'nationality_group_id', 'name', 'starts_on', 'ends_on', 'recurring_yearly', 'is_active',
    'adult_price', 'child_price', 'unit_prices', 'tier_prices', 'price_per_night', 'price',
])]
class SeasonPrice extends Model
{
    /** @use HasFactory<SeasonPriceFactory> */
    use HasFactory, HasPriceMaps, HasTranslations;

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
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'recurring_yearly' => 'boolean',
            'is_active' => 'boolean',
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

    /**
     * Whether this season can apply to the given group (all-groups seasons apply to every group).
     */
    public function appliesToGroup(?int $groupId): bool
    {
        return $this->nationality_group_id === null || $this->nationality_group_id === $groupId;
    }

    /**
     * Whether the (inclusive) period contains the given date.
     * Recurring periods compare month/day only and may wrap the year end (20 Dec - 6 Jan).
     */
    public function covers(CarbonInterface $date): bool
    {
        if (! $this->recurring_yearly) {
            $day = $date->format('Y-m-d');

            return $day >= $this->starts_on->format('Y-m-d') && $day <= $this->ends_on->format('Y-m-d');
        }

        $day = $date->format('m-d');
        $start = $this->starts_on->format('m-d');
        $end = $this->ends_on->format('m-d');

        if ($start <= $end) {
            return $day >= $start && $day <= $end;
        }

        return $day >= $start || $day <= $end;
    }

    /**
     * Whether the period is current or will apply in the future relative to the given day.
     */
    public function isCurrentOrFuture(CarbonInterface $today): bool
    {
        return $this->recurring_yearly || $this->ends_on->format('Y-m-d') >= $today->format('Y-m-d');
    }

    /**
     * Row for the admin price matrix season editor.
     *
     * @return array<string, mixed>
     */
    public function toEditorRow(): array
    {
        return [
            'id' => $this->id,
            'nationality_group_id' => $this->nationality_group_id,
            'name' => $this->getTranslations('name'),
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'recurring_yearly' => $this->recurring_yearly,
            'is_active' => $this->is_active,
            'adult_price' => $this->adult_price,
            'child_price' => $this->child_price,
            'price_per_night' => $this->price_per_night,
            'price' => $this->price,
            'unit_prices' => $this->unit_prices ?? [],
            'tier_prices' => $this->tier_prices ?? [],
        ];
    }
}
