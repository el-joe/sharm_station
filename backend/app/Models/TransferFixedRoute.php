<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\TransferFixedRouteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fixed price from a hub to a popular place; overrides the distance formula (SRS 5.1).
 * A null vehicle type means the price applies to every vehicle, multiplied by its multiplier.
 */
#[Fillable(['transfer_hub_id', 'vehicle_type_id', 'destination_name', 'lat', 'lng', 'match_radius_km', 'price', 'partner_price', 'is_enabled'])]
class TransferFixedRoute extends Model
{
    /** @use HasFactory<TransferFixedRouteFactory> */
    use HasFactory, HasTranslations;

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
            'partner_price' => 'decimal:2',
            'is_enabled' => 'boolean',
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
}
