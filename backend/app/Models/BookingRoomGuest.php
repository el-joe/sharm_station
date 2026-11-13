<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_room_id', 'booking_traveler_id', 'guest_price', 'discount_applied', 'extra_bed_fee'])]
class BookingRoomGuest extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'guest_price' => 'decimal:2',
            'discount_applied' => 'decimal:2',
            'extra_bed_fee' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<BookingRoom, $this>
     */
    public function bookingRoom(): BelongsTo
    {
        return $this->belongsTo(BookingRoom::class);
    }

    /**
     * @return BelongsTo<BookingTraveler, $this>
     */
    public function traveler(): BelongsTo
    {
        return $this->belongsTo(BookingTraveler::class, 'booking_traveler_id');
    }
}
