<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['package_stage_id', 'trip_id', 'day_number', 'sort'])]
class PackageStageTrip extends Model
{
    /**
     * @return BelongsTo<PackageStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(PackageStage::class, 'package_stage_id');
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
