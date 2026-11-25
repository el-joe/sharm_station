<?php

namespace App\Models;

use Database\Factories\RoomIcalFeedFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * External calendar (Booking.com, Airbnb...) imported into a room: its events become closed nights (SRS 6.2).
 */
#[Fillable(['room_id', 'name', 'url', 'is_active', 'last_synced_at', 'last_nights', 'last_error'])]
class RoomIcalFeed extends Model
{
    /** @use HasFactory<RoomIcalFeedFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_synced_at' => 'immutable_datetime',
            'last_nights' => 'integer',
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
     * @return HasMany<RoomAvailability, $this>
     */
    public function nights(): HasMany
    {
        return $this->hasMany(RoomAvailability::class);
    }
}
