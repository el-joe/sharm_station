<?php

namespace App\Models;

use App\Enums\TravelerType;
use Database\Factories\BookingTravelerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'first_name', 'last_name', 'type', 'age', 'nationality', 'passport_number', 'sort'])]
class BookingTraveler extends Model
{
    /** @use HasFactory<BookingTravelerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TravelerType::class,
            'age' => 'integer',
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
