<?php

namespace App\Models;

use Database\Factories\TripPrivateTierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trip_id', 'min_people', 'max_people', 'price_per_person', 'partner_price'])]
class TripPrivateTier extends Model
{
    /** @use HasFactory<TripPrivateTierFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_people' => 'integer',
            'max_people' => 'integer',
            'price_per_person' => 'decimal:2',
            'partner_price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function covers(int $persons): bool
    {
        return $persons >= $this->min_people && ($this->max_people === null || $persons <= $this->max_people);
    }

    public function label(): string
    {
        if ($this->max_people === null) {
            return $this->min_people.'+';
        }

        return $this->min_people === $this->max_people ? (string) $this->min_people : $this->min_people.'-'.$this->max_people;
    }
}
