<?php

namespace App\Models;

use App\Enums\TransferStatus;
use App\Enums\VehicleType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Included-transfer task created for every package booking (SRS 4.2). Carries no price.
 */
#[Fillable([
    'booking_id', 'package_included_transfer_id', 'from_place', 'to_place', 'date', 'time', 'vehicle_type', 'passengers',
    'status', 'driver_name', 'driver_phone', 'car_plate', 'notes',
])]
class BookingIncludedTransfer extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'vehicle_type' => VehicleType::class,
            'status' => TransferStatus::class,
            'passengers' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
