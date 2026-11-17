<?php

namespace App\Models;

use App\Models\Concerns\HasProductPrices;
use App\Models\Concerns\HasSeasonPrices;
use App\Models\Concerns\HasTranslations;
use App\Models\Contracts\Priceable;
use Database\Factories\TransferFixedRouteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fixed price from a hub to a popular place; overrides the distance formula (SRS 5.1).
 * A null vehicle type means the price applies to every vehicle, multiplied by its multiplier.
 */
#[Fillable(['transfer_hub_id', 'vehicle_type_id', 'destination_name', 'lat', 'lng', 'match_radius_km', 'price', 'is_enabled', 'seo'])]
class TransferFixedRoute extends Model implements Priceable
{
    /** @use HasFactory<TransferFixedRouteFactory> */
    use HasFactory, HasProductPrices, HasSeasonPrices, HasTranslations;

    /**
     * @var list<string>
     */
    protected array $translatable = ['destination_name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'match_radius_km' => 'float',
            'price' => 'decimal:2',
            'is_enabled' => 'boolean',
            'seo' => 'array',
        ];
    }

    /**
     * @return BelongsTo<TransferHub, $this>
     */
    public function hub(): BelongsTo
    {
        return $this->belongsTo(TransferHub::class, 'transfer_hub_id');
    }

    /**
     * @return BelongsTo<VehicleType, $this>
     */
    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function basePriceFor(string $field): ?float
    {
        return $field === 'price' && $this->price !== null ? (float) $this->price : null;
    }

    public function priceFields(): array
    {
        return ['price' => 'Price per vehicle'];
    }
}
