<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Carbon\CarbonInterface;
use Database\Factories\SeasonPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A price period of a trip, package or room.
 *
 * unit_prices / tier_prices map option or tier ids to either a retail number
 * or an object {"price": 40, "partner_price": 34}.
 */
#[Fillable([
    'priceable_type', 'priceable_id', 'name', 'starts_on', 'ends_on', 'recurring_yearly', 'is_active',
    'adult_price', 'child_price', 'partner_price', 'unit_prices', 'tier_prices', 'price_per_night',
])]
class SeasonPrice extends Model
{
    /** @use HasFactory<SeasonPriceFactory> */
    use HasFactory, HasTranslations;

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
            'partner_price' => 'decimal:2',
            'price_per_night' => 'decimal:2',
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

    public function unitPriceFor(int $unitOptionId): ?float
    {
        return $this->mapPrice($this->unit_prices, $unitOptionId, 'price');
    }

    public function unitPartnerPriceFor(int $unitOptionId): ?float
    {
        return $this->mapPrice($this->unit_prices, $unitOptionId, 'partner_price');
    }

    public function tierPriceFor(int $tierId): ?float
    {
        return $this->mapPrice($this->tier_prices, $tierId, 'price');
    }

    public function tierPartnerPriceFor(int $tierId): ?float
    {
        return $this->mapPrice($this->tier_prices, $tierId, 'partner_price');
    }

    /**
     * @param  array<int|string, mixed>|null  $map
     */
    private function mapPrice(?array $map, int $id, string $field): ?float
    {
        $value = $map[$id] ?? $map[(string) $id] ?? null;

        if (is_array($value)) {
            $value = $value[$field] ?? null;
        } elseif ($field !== 'price') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Normalised row for the admin SeasonPriceEditor (maps become {id: {price, partner_price}}).
     *
     * @return array<string, mixed>
     */
    public function toEditorRow(): array
    {
        $map = function (?array $prices): array {
            $rows = [];

            foreach ($prices ?? [] as $id => $value) {
                $rows[$id] = is_array($value)
                    ? ['price' => $value['price'] ?? null, 'partner_price' => $value['partner_price'] ?? null]
                    : ['price' => $value, 'partner_price' => null];
            }

            return $rows;
        };

        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'recurring_yearly' => $this->recurring_yearly,
            'is_active' => $this->is_active,
            'adult_price' => $this->adult_price,
            'child_price' => $this->child_price,
            'partner_price' => $this->partner_price,
            'price_per_night' => $this->price_per_night,
            'unit_prices' => $map($this->unit_prices),
            'tier_prices' => $map($this->tier_prices),
        ];
    }
}
