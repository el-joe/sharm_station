<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\TripTimelineItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trip_day_id', 'time_from', 'time_to', 'title', 'description', 'place', 'image', 'transfer_included', 'sort'])]
class TripTimelineItem extends Model
{
    /** @use HasFactory<TripTimelineItemFactory> */
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
        return ['transfer_included' => 'boolean'];
    }

    /**
     * @return BelongsTo<TripDay, $this>
     */
    public function day(): BelongsTo
    {
        return $this->belongsTo(TripDay::class, 'trip_day_id');
    }
}
