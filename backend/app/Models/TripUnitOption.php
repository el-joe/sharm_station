<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\TripUnitOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trip_id', 'name', 'seats', 'price', 'max_quantity', 'children_allowed', 'sort'])]
class TripUnitOption extends Model
{
    /** @use HasFactory<TripUnitOptionFactory> */
    use HasFactory, HasTranslations;

    /**
     * @var list<string>
     */
    protected array $translatable = ['name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'max_quantity' => 'integer',
            'price' => 'decimal:2',
            'children_allowed' => 'boolean',
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
