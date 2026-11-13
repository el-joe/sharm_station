<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['booking_id', 'room_id', 'room_name', 'room_number', 'pricing_method', 'nights', 'price', 'total', 'partner_total'])]
class BookingRoom extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nights' => 'integer',
            'price' => 'decimal:2',
            'total' => 'decimal:2',
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
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return HasMany<BookingRoomGuest, $this>
     */
    public function guests(): HasMany
    {
        return $this->hasMany(BookingRoomGuest::class);
    }
}
