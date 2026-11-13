<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\TripMediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'trip_media')]
#[Fillable(['trip_id', 'path', 'alt', 'sort'])]
class TripMedia extends Model
{
    /** @use HasFactory<TripMediaFactory> */
    use HasFactory, HasTranslations;

    /**
     * @var list<string>
     */
    protected array $translatable = ['alt'];

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
