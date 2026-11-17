<?php

namespace App\Models;

use App\Enums\RoomAvailabilityStatus;
use App\Enums\RoomPricingMode;
use App\Enums\RoomStatus;
use App\Enums\RoomType;
use App\Models\Concerns\HasChildPolicy;
use App\Models\Concerns\HasProductPrices;
use App\Models\Concerns\HasSeasonPrices;
use App\Models\Concerns\HasTranslations;
use App\Models\Contracts\Priceable;
use Carbon\CarbonInterface;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'building_id', 'name', 'number', 'room_type', 'max_adults', 'max_children', 'max_total', 'beds', 'size_m2', 'view',
    'description', 'amenities', 'cover_image', 'pricing_mode', 'base_price', 'extra_bed_price', 'from_price',
    'min_nights', 'max_nights', 'status', 'sort', 'seo',
])]
class Room extends Model implements Priceable
{
    /** @use HasFactory<RoomFactory> */
    use HasChildPolicy, HasFactory, HasProductPrices, HasSeasonPrices, HasTranslations;

    /**
     * @var list<string>
     */
    protected array $translatable = ['name', 'beds', 'view', 'description'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'room_type' => RoomType::class,
            'pricing_mode' => RoomPricingMode::class,
            'status' => RoomStatus::class,
            'amenities' => 'array',
            'seo' => 'array',
            'max_adults' => 'integer',
            'max_children' => 'integer',
            'max_total' => 'integer',
            'min_nights' => 'integer',
            'max_nights' => 'integer',
            'base_price' => 'decimal:2',
            'extra_bed_price' => 'decimal:2',
            'from_price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Building, $this>
     */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /**
     * @return HasMany<RoomAvailability, $this>
     */
    public function availability(): HasMany
    {
        return $this->hasMany(RoomAvailability::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withAvailabilitySummary(Builder $query): void
    {
        $query->withCount(['availability as blocked_nights_30' => fn (Builder $days) => self::blockingDays($days)
            ->whereBetween('date', [now()->toDateString(), now()->addDays(29)->toDateString()])]);
    }

    /**
     * Rooms with no blocking night (booked, closed or an unexpired hold) between check-in and the night before check-out.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function availableBetween(Builder $query, CarbonInterface $checkIn, CarbonInterface $checkOut): void
    {
        $query->whereDoesntHave('availability', fn (Builder $days) => self::blockingDays($days)
            ->whereBetween('date', [$checkIn->toDateString(), $checkOut->copy()->subDay()->toDateString()]));
    }

    /**
     * @param  Builder<RoomAvailability>  $days
     * @return Builder<RoomAvailability>
     */
    public static function blockingDays(Builder $days): Builder
    {
        return $days->where(fn (Builder $status) => $status
            ->whereIn('status', [RoomAvailabilityStatus::Booked, RoomAvailabilityStatus::Closed])
            ->orWhere(fn (Builder $held) => $held->where('status', RoomAvailabilityStatus::Held)
                ->where(fn (Builder $until) => $until->whereNull('held_until')->orWhere('held_until', '>', now()))));
    }

    /**
     * "Few left": at most 7 bookable nights in the next 30 days (needs withAvailabilitySummary()).
     */
    public function hasFewNightsLeft(): bool
    {
        $blocked = $this->getAttribute('blocked_nights_30');

        return $blocked !== null && (30 - (int) $blocked) <= 7;
    }

    public function includesBreakfast(): bool
    {
        return in_array('breakfast', [...($this->amenities ?? []), ...($this->building?->amenities ?? [])], true);
    }

    #[Scope]
    protected function bookable(Builder $query): void
    {
        $query->where('status', RoomStatus::Active)
            ->whereHas('building', fn (Builder $building) => $building->published());
    }

    public function basePriceFor(string $field): ?float
    {
        return $field === 'price_per_night' && $this->base_price !== null ? (float) $this->base_price : null;
    }

    public function priceFields(): array
    {
        return ['price_per_night' => $this->pricing_mode === RoomPricingMode::PerPerson ? 'Per person / night' : 'Per night'];
    }
}
