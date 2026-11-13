<?php

namespace App\Models;

use App\Enums\BookingType;
use Database\Factories\TripAvailabilityRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Weekdays use ISO-8601 numbers (1 = Monday ... 7 = Sunday). Dates are Y-m-d strings.
 */
#[Fillable(['trip_id', 'booking_type', 'weekdays', 'dates', 'blackout_dates', 'lead_time_hours'])]
class TripAvailabilityRule extends Model
{
    /** @use HasFactory<TripAvailabilityRuleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'booking_type' => BookingType::class,
            'weekdays' => 'array',
            'dates' => 'array',
            'blackout_dates' => 'array',
            'lead_time_hours' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
