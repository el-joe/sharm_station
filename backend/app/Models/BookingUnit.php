<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'trip_unit_option_id', 'name', 'seats', 'quantity', 'unit_price', 'total', 'partner_unit_price', 'partner_total'])]
class BookingUnit extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
            'partner_unit_price' => 'decimal:2',
            'partner_total' => 'decimal:2',
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
     * @return BelongsTo<TripUnitOption, $this>
     */
    public function unitOption(): BelongsTo
    {
        return $this->belongsTo(TripUnitOption::class, 'trip_unit_option_id');
    }
}
