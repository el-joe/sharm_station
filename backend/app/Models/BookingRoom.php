<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'booking_id', 'room_id', 'room_option_id', 'stage_position', 'room_name', 'room_number', 'pricing_method', 'adults', 'child_ages',
    'extra_beds', 'cots', 'nights', 'price', 'total', 'extras_total', 'partner_total',
])]
class BookingRoom extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nights' => 'integer',
            'stage_position' => 'integer',
            'adults' => 'integer',
            'child_ages' => 'array',
            'extra_beds' => 'integer',
            'cots' => 'integer',
            'extras_total' => 'decimal:2',
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
     * @return BelongsTo<RoomOption, $this>
     */
    public function roomOption(): BelongsTo
    {
        return $this->belongsTo(RoomOption::class);
    }

    /**
     * Guests allocated to this room (adults + children).
     */
    public function occupants(): int
    {
        return $this->adults + count($this->child_ages ?? []);
    }

    /**
     * @return HasMany<BookingRoomGuest, $this>
     */
    public function guests(): HasMany
    {
        return $this->hasMany(BookingRoomGuest::class);
    }
}
