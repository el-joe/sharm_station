<?php

namespace App\Models;

use App\Enums\RoomAvailabilityStatus;
use Database\Factories\RoomAvailabilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'room_availability')]
#[Fillable(['room_id', 'date', 'status', 'booking_id', 'room_ical_feed_id', 'price_override', 'held_until'])]
class RoomAvailability extends Model
{
    /** @use HasFactory<RoomAvailabilityFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'status' => RoomAvailabilityStatus::class,
            'price_override' => 'decimal:2',
            'held_until' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<RoomIcalFeed, $this>
     */
    public function icalFeed(): BelongsTo
    {
        return $this->belongsTo(RoomIcalFeed::class, 'room_ical_feed_id');
    }

    /**
     * A held night blocks the room until the hold expires.
     */
    public function blocksBooking(): bool
    {
        return match ($this->status) {
            RoomAvailabilityStatus::Open => false,
            RoomAvailabilityStatus::Held => $this->held_until === null || $this->held_until->isFuture(),
            default => true,
        };
    }
}
