<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Activity\ActivityFilterData;
use App\Models\ActivityLog;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<ActivityLog>
 */
class ActivityLogRepository extends BaseRepository implements ActivityLogRepositoryInterface
{
    protected function model(): string
    {
        return ActivityLog::class;
    }

    public function adminQuery(ActivityFilterData $filters): Builder
    {
        return $this->query()
            ->select('activity_logs.*')
            ->with('causer:id,name,email')
            ->when($filters->causerId, fn (Builder $query, int $id) => $query->where('causer_id', $id))
            ->when($filters->action, fn (Builder $query, $action) => $query->where('action', $action))
            ->when($filters->subjectType, fn (Builder $query, string $type) => $query->where('subject_type', $type))
            ->when($filters->dateFrom, fn (Builder $query, CarbonInterface $from) => $query->where('created_at', '>=', $from->startOfDay()))
            ->when($filters->dateTo, fn (Builder $query, CarbonInterface $to) => $query->where('created_at', '<=', $to->endOfDay()))
            ->when($filters->search, fn (Builder $query, string $search) => $query->where('description', 'like', "%{$search}%"));
    }

    public function subjectTypes(): array
    {
        return $this->query()->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type')->all();
    }
}
