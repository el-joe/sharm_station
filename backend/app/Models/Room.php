<?php

namespace App\Models;

use App\Enums\RoomAvailabilityStatus;
use App\Enums\RoomPricingMode;
use App\Enums\RoomStatus;
use App\Enums\RoomType;
use App\Enums\RoomUnavailableReason;
use App\Models\Concerns\HasChildPolicy;
use App\Models\Concerns\HasProductPrices;
use App\Models\Concerns\HasSeasonPrices;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
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
    'description', 'amenities', 'cover_image', 'pricing_mode', 'base_price', 'extra_bed_price', 'max_extra_beds', 'cot_price', 'max_cots', 'from_price',
    'min_nights', 'max_nights', 'status', 'sort', 'seo', 'ical_token',
])]
class Room extends Model implements Priceable
{
    /** @use HasFactory<RoomFactory> */
    use HasChildPolicy, HasFactory, HasProductPrices, HasSeasonPrices, HasTranslations, LogsActivity;

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
            'max_extra_beds' => 'integer',
            'cot_price' => 'decimal:2',
            'max_cots' => 'integer',
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
     * @return HasMany<RoomIcalFeed, $this>
     */
    public function icalFeeds(): HasMany
    {
        return $this->hasMany(RoomIcalFeed::class)->orderBy('id');
    }

    /**
     * Guests the room can sleep with the given number of extra beds (each extra bed adds one place).
     */
    public function capacityWith(int $extraBeds = 0): int
    {
        return $this->max_total + max(0, min($extraBeds, $this->max_extra_beds));
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
     * Nights taken by a booking: booked, or held with an unexpired hold.
     *
     * @param  Builder<RoomAvailability>  $days
     * @return Builder<RoomAvailability>
     */
    public static function bookedDays(Builder $days): Builder
    {
        return $days->where(fn (Builder $status) => $status
            ->where('status', RoomAvailabilityStatus::Booked)
            ->orWhere(fn (Builder $held) => $held->where('status', RoomAvailabilityStatus::Held)
                ->where(fn (Builder $until) => $until->whereNull('held_until')->orWhere('held_until', '>', now()))));
    }

    /**
     * Stay annotations for the listing (SRS 6.3): whether a night of the stay is booked/held or closed.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withStayAvailability(Builder $query, CarbonInterface $checkIn, CarbonInterface $checkOut): void
    {
        $range = [$checkIn->toDateString(), $checkOut->copy()->subDay()->toDateString()];

        $query->withExists([
            'availability as stay_booked' => fn (Builder $days) => self::bookedDays($days)->whereBetween('date', $range),
            'availability as stay_closed' => fn (Builder $days) => $days->where('status', RoomAvailabilityStatus::Closed)->whereBetween('date', $range),
        ]);
    }

    /**
     * Why the room cannot be booked for the searched stay and party (null = bookable), most important first.
     *
     * @return list<RoomUnavailableReason>
     */
    public function unavailableReasons(?int $nights, ?int $guests): array
    {
        $reasons = [];

        if ($this->getAttribute('stay_booked')) {
            $reasons[] = RoomUnavailableReason::Booked;
        }

        if ($this->getAttribute('stay_closed')) {
            $reasons[] = RoomUnavailableReason::Closed;
        }

        if ($nights !== null && ($nights < $this->min_nights || ($this->max_nights !== null && $nights > $this->max_nights))) {
            $reasons[] = RoomUnavailableReason::MinStay;
        }

        if ($guests !== null && $guests > $this->capacityWith($this->max_extra_beds)) {
            $reasons[] = RoomUnavailableReason::Capacity;
        }

        return $reasons;
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
