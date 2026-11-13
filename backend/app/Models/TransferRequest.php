<?php

namespace App\Models;

use App\Enums\TransferDirection;
use App\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A paid standalone transfer: route, distance, pricing breakdown and the operational follow-up (SRS 5.4, 5.5).
 */
#[Fillable([
    'booking_id', 'transfer_hub_id', 'vehicle_type_id', 'transfer_fixed_route_id', 'direction', 'destination_name',
    'destination_lat', 'destination_lng', 'pickup_at', 'return_at', 'round_trip', 'passengers', 'luggage', 'extras',
    'flight_number', 'distance_km', 'duration_minutes', 'distance_provider', 'needs_review', 'breakdown', 'price', 'status',
    'driver_name', 'driver_phone', 'car_type', 'car_plate', 'admin_notes',
])]
class TransferRequest extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => TransferDirection::class,
            'status' => TransferStatus::class,
            'pickup_at' => 'immutable_datetime',
            'return_at' => 'immutable_datetime',
            'round_trip' => 'boolean',
            'needs_review' => 'boolean',
            'extras' => 'array',
            'breakdown' => 'array',
            'distance_km' => 'decimal:2',
            'destination_lat' => 'float',
            'destination_lng' => 'float',
            'price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
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
