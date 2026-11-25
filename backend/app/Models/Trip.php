<?php

namespace App\Models;

use App\Enums\BookingType;
use App\Enums\PricingMode;
use App\Enums\PublishStatus;
use App\Enums\TripType;
use App\Models\Concerns\HasAddons;
use App\Models\Concerns\HasChildPolicy;
use App\Models\Concerns\HasProductPrices;
use App\Models\Concerns\HasSeasonPrices;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\RecordsSlugRedirects;
use App\Models\Contracts\Priceable;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'type', 'destination_id', 'category', 'title', 'slug', 'summary', 'description', 'duration_hours', 'days', 'nights',
    'private_enabled', 'group_enabled', 'pricing_mode', 'min_persons', 'max_persons', 'base_price', 'base_child_price',
    'from_price', 'deposit_percent', 'free_cancellation_hours', 'highlights', 'included', 'excluded',
    'meeting_point', 'lat', 'lng', 'vessel_name', 'itinerary_from', 'itinerary_to', 'vessel_facilities', 'meal_type',
    'cover_image', 'status', 'seo', 'featured', 'sort', 'unit_label', 'faq', 'what_to_bring', 'guide_languages',
    'cancellation_policy', 'cabins',
])]
class Trip extends Model implements Priceable
{
    /** @use HasFactory<TripFactory> */
    use HasAddons, HasChildPolicy, HasFactory, HasProductPrices, HasSeasonPrices, HasTranslations, LogsActivity, RecordsSlugRedirects;

    /**
     * @var list<string>
     */
    protected array $translatable = ['title', 'summary', 'description', 'highlights', 'included', 'excluded', 'meeting_point', 'unit_label', 'faq', 'what_to_bring', 'cancellation_policy'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TripType::class,
            'pricing_mode' => PricingMode::class,
            'status' => PublishStatus::class,
            'private_enabled' => 'boolean',
            'group_enabled' => 'boolean',
            'featured' => 'boolean',
            'duration_hours' => 'decimal:2',
            'base_price' => 'decimal:2',
            'base_child_price' => 'decimal:2',
            'from_price' => 'decimal:2',
            'deposit_percent' => 'decimal:2',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'vessel_facilities' => 'array',
            'seo' => 'array',
            'guide_languages' => 'array',
            'cabins' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Destination, $this>
     */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    /**
     * @return HasMany<TripUnitOption, $this>
     */
    public function unitOptions(): HasMany
    {
        return $this->hasMany(TripUnitOption::class)->orderBy('sort');
    }

    /**
     * @return HasMany<TripPrivateTier, $this>
     */
    public function privateTiers(): HasMany
    {
        return $this->hasMany(TripPrivateTier::class)->orderBy('min_people');
    }

    /**
     * @return HasMany<TripAvailabilityRule, $this>
     */
    public function availabilityRules(): HasMany
    {
        return $this->hasMany(TripAvailabilityRule::class);
    }

    /**
     * @return HasMany<TripDay, $this>
     */
    public function tripDays(): HasMany
    {
        return $this->hasMany(TripDay::class)->orderBy('day_number');
    }

    /**
     * @return HasManyThrough<TripTimelineItem, TripDay, $this>
     */
    public function timelineItems(): HasManyThrough
    {
        return $this->hasManyThrough(TripTimelineItem::class, TripDay::class);
    }

    /**
     * @return HasMany<TripMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(TripMedia::class)->orderBy('sort');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', PublishStatus::Published);
    }

    public function supportsBookingType(BookingType $bookingType): bool
    {
        return match ($bookingType) {
            BookingType::Private => $this->private_enabled,
            BookingType::Group => $this->group_enabled,
        };
    }

    /**
     * @return list<BookingType>
     */
    public function bookingTypes(): array
    {
        return array_values(array_filter(
            BookingType::cases(),
            fn (BookingType $type): bool => $this->supportsBookingType($type),
        ));
    }

    public function basePriceFor(string $field): ?float
    {
        $value = match (true) {
            $field === 'adult_price' => $this->base_price,
            $field === 'child_price' => $this->base_child_price,
            str_starts_with($field, 'tier_prices.') => $this->privateTiers->firstWhere('id', (int) substr($field, 12))?->price_per_person,
            str_starts_with($field, 'unit_prices.') => $this->unitOptions->firstWhere('id', (int) substr($field, 12))?->price,
            default => null,
        };

        return $value !== null ? (float) $value : null;
    }

    public function priceFields(): array
    {
        if ($this->pricing_mode === PricingMode::PerUnit) {
            return $this->unitOptions->mapWithKeys(fn (TripUnitOption $option): array => ["unit_prices.{$option->id}" => (string) $option->name])->all();
        }

        $fields = [];

        if ($this->group_enabled) {
            $fields['adult_price'] = 'Adult';
            $fields['child_price'] = 'Child';
        }

        if ($this->private_enabled) {
            foreach ($this->privateTiers as $tier) {
                $range = $tier->min_people.($tier->max_people ? '–'.$tier->max_people : '+');
                $fields["tier_prices.{$tier->id}"] = "Private {$range} pax";
            }
        }

        return $fields;
    }

    /**
     * Optional rooms the customer can add (SRS 7.1), in display order.
     *
     * @return MorphMany<RoomOption, $this>
     */
    public function roomOptions(): MorphMany
    {
        return $this->morphMany(RoomOption::class, 'owner')->orderBy('stage_position')->orderBy('sort')->orderBy('id');
    }
}
