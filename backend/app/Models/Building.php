<?php

namespace App\Models;

use App\Enums\BuildingType;
use App\Enums\PublishStatus;
use App\Models\Concerns\HasChildPolicy;
use App\Models\Concerns\HasTranslations;
use Database\Factories\BuildingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'slug', 'type', 'destination_id', 'address', 'lat', 'lng', 'stars', 'description', 'amenities', 'cover_image',
    'check_in_from', 'check_out_until', 'deposit_percent', 'cancellation_policy', 'free_cancellation_hours', 'taxes_included', 'tax_per_night',
    'google_place_id', 'phone', 'email', 'status', 'featured', 'seo',
])]
class Building extends Model
{
    /** @use HasFactory<BuildingFactory> */
    use HasChildPolicy, HasFactory, HasTranslations;

    /**
     * @var list<string>
     */
    protected array $translatable = ['name', 'description', 'cancellation_policy'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BuildingType::class,
            'status' => PublishStatus::class,
            'amenities' => 'array',
            'seo' => 'array',
            'stars' => 'integer',
            'free_cancellation_hours' => 'integer',
            'deposit_percent' => 'decimal:2',
            'taxes_included' => 'boolean',
            'tax_per_night' => 'decimal:2',
            'featured' => 'boolean',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
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
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class)->orderBy('sort');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', PublishStatus::Published);
    }
}
