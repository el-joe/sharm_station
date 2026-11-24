<?php

namespace App\Models;

use App\Enums\HubType;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
use Database\Factories\TransferHubFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Departure point with its own pricing settings (SRS 5.1, 5.2).
 *
 * distance_tiers: list of {up_to_km: float|null, rate: float} in ascending order (null = beyond the last limit).
 * extras: list of {code: string, label: string, price: float}.
 */
#[Fillable([
    'name', 'slug', 'type', 'destination_id', 'lat', 'lng', 'radius_km', 'max_distance_km', 'base_fare', 'distance_tiers',
    'min_fare', 'waiting_grace_minutes', 'waiting_rate_per_hour', 'night_surcharge', 'fixed_fees', 'extras',
    'return_discount_percent', 'deposit_percent', 'min_notice_hours', 'vehicle_type_ids', 'is_enabled', 'sort',
])]
class TransferHub extends Model
{
    /** @use HasFactory<TransferHubFactory> */
    use HasFactory, HasTranslations, LogsActivity;

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
            'type' => HubType::class,
            'lat' => 'float',
            'lng' => 'float',
            'radius_km' => 'float',
            'max_distance_km' => 'float',
            'base_fare' => 'decimal:2',
            'min_fare' => 'decimal:2',
            'waiting_rate_per_hour' => 'decimal:2',
            'night_surcharge' => 'decimal:2',
            'fixed_fees' => 'decimal:2',
            'return_discount_percent' => 'decimal:2',
            'deposit_percent' => 'decimal:2',
            'distance_tiers' => 'array',
            'extras' => 'array',
            'vehicle_type_ids' => 'array',
            'is_enabled' => 'boolean',
            'waiting_grace_minutes' => 'integer',
            'min_notice_hours' => 'integer',
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
     * @return HasMany<TransferFixedRoute, $this>
     */
    public function fixedRoutes(): HasMany
    {
        return $this->hasMany(TransferFixedRoute::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function enabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }

    public function allowsVehicle(VehicleType $vehicle): bool
    {
        return empty($this->vehicle_type_ids) || in_array($vehicle->id, array_map('intval', $this->vehicle_type_ids), true);
    }

    /**
     * @return array{code: string, label: string, price: float}|null
     */
    public function extra(string $code): ?array
    {
        foreach ($this->extras ?? [] as $extra) {
            if (($extra['code'] ?? null) === $code) {
                return ['code' => $code, 'label' => (string) ($extra['label'] ?? $code), 'price' => (float) ($extra['price'] ?? 0)];
            }
        }

        return null;
    }
}
