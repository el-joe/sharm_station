<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\PackageStageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['package_id', 'destination_id', 'position', 'days', 'nights', 'accommodation'])]
class PackageStage extends Model
{
    /** @use HasFactory<PackageStageFactory> */
    use HasFactory, HasTranslations;

    /**
     * @var list<string>
     */
    protected array $translatable = ['accommodation'];

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * @return BelongsTo<Destination, $this>
     */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    /**
     * @return HasMany<PackageStageTrip, $this>
     */
    public function stageTrips(): HasMany
    {
        return $this->hasMany(PackageStageTrip::class)->orderBy('day_number')->orderBy('sort');
    }
}
