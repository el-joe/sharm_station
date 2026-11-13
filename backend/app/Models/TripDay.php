<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\TripDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['trip_id', 'day_number', 'title', 'description', 'meals', 'overnight'])]
class TripDay extends Model
{
    /** @use HasFactory<TripDayFactory> */
    use HasFactory, HasTranslations;

    /**
     * @var list<string>
     */
    protected array $translatable = ['title', 'description'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_number' => 'integer',
            'meals' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * @return HasMany<TripTimelineItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TripTimelineItem::class)->orderBy('sort');
    }
}
