<?php

namespace App\Models\Concerns;

use App\Enums\ActivityAction;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Records create/update/delete in the activity log when a staff member makes the change in the admin panel.
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn (Model $model) => app(ActivityLogger::class)->modelEvent(ActivityAction::Created, $model));
        static::updated(fn (Model $model) => app(ActivityLogger::class)->modelEvent(ActivityAction::Updated, $model));
        static::deleted(fn (Model $model) => app(ActivityLogger::class)->modelEvent(ActivityAction::Deleted, $model));
    }
}
