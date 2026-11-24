<?php

namespace App\Models;

use App\Enums\VehicleType as VehicleCategory;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
use Database\Factories\VehicleTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Vehicle offered for transfers, with capacity and price multiplier V (SRS 5.2).
 */
#[Fillable(['name', 'category', 'capacity', 'luggage', 'multiplier', 'min_fare', 'image', 'is_enabled', 'sort'])]
class VehicleType extends Model
{
    /** @use HasFactory<VehicleTypeFactory> */
    use HasFactory, HasTranslations, LogsActivity;

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
            'category' => VehicleCategory::class,
            'capacity' => 'integer',
            'luggage' => 'integer',
            'multiplier' => 'decimal:2',
            'min_fare' => 'decimal:2',
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function enabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }
}
