<?php

namespace App\Models;

use App\Enums\BookingType;
use App\Enums\PackagePriceMode;
use App\Enums\PricingMode;
use App\Enums\PublishStatus;
use App\Models\Concerns\HasAddons;
use App\Models\Concerns\HasChildPolicy;
use App\Models\Concerns\HasProductPrices;
use App\Models\Concerns\HasSeasonPrices;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\RecordsSlugRedirects;
use App\Models\Contracts\Priceable;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'destination_id', 'title', 'slug', 'summary', 'description', 'days', 'nights', 'private_enabled', 'group_enabled',
    'pricing_mode', 'min_persons', 'max_persons', 'price_mode', 'base_price', 'base_child_price',
    'discount_percent', 'from_price', 'deposit_percent', 'free_cancellation_hours', 'highlights', 'included', 'excluded',
    'cover_image', 'status', 'seo', 'featured', 'sort', 'transfers_included', 'faq', 'what_to_bring', 'guide_languages',
    'cancellation_policy',
])]
class Package extends Model implements Priceable
{
    /** @use HasFactory<PackageFactory> */
    use HasAddons, HasChildPolicy, HasFactory, HasProductPrices, HasSeasonPrices, HasTranslations, LogsActivity, RecordsSlugRedirects;

    /**
     * Relations the "from" price of a package priced from its trips needs (eager load them on listings).
     *
     * @var list<string>
     */
    public const COMPUTED_PRICE_RELATIONS = [
        'stages.stageTrips.trip.unitOptions', 'stages.stageTrips.trip.privateTiers',
        'stages.stageTrips.trip.seasonPrices', 'stages.stageTrips.trip.productPrices',
    ];

    /**
     * @var list<string>
     */
    protected array $translatable = ['title', 'summary', 'description', 'highlights', 'included', 'excluded', 'faq', 'what_to_bring', 'cancellation_policy'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pricing_mode' => PricingMode::class,
            'price_mode' => PackagePriceMode::class,
            'status' => PublishStatus::class,
            'private_enabled' => 'boolean',
            'group_enabled' => 'boolean',
            'featured' => 'boolean',
            'base_price' => 'decimal:2',
            'base_child_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'from_price' => 'decimal:2',
            'deposit_percent' => 'decimal:2',
            'seo' => 'array',
            'transfers_included' => 'boolean',
            'guide_languages' => 'array',
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
     * @return HasMany<PackageStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(PackageStage::class)->orderBy('position');
    }

    /**
     * @return HasMany<PackageIncludedTransfer, $this>
     */
    public function includedTransfers(): HasMany
    {
        return $this->hasMany(PackageIncludedTransfer::class)->orderBy('day_number');
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
        return array_values(array_filter(BookingType::cases(), fn (BookingType $type): bool => $this->supportsBookingType($type)));
    }

    /**
     * First package day of each stage, keyed by stage id (stages are consecutive).
     *
     * @return array<int, int>
     */
    public function stageStartDays(): array
    {
        $start = 1;
        $days = [];

        foreach ($this->stages as $stage) {
            $days[$stage->id] = $start;
            $start += max(1, (int) $stage->days);
        }

        return $days;
    }

    public function basePriceFor(string $field): ?float
    {
        $value = match ($field) {
            'adult_price' => $this->base_price,
            'child_price' => $this->base_child_price,
            default => null,
        };

        return $value !== null ? (float) $value : null;
    }

    public function priceFields(): array
    {
        return ['adult_price' => 'Adult', 'child_price' => 'Child'];
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
