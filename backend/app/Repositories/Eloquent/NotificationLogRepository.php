<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Notification\NotificationLogFilterData;
use App\Models\NotificationLog;
use App\Repositories\Contracts\NotificationLogRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<NotificationLog>
 */
class NotificationLogRepository extends BaseRepository implements NotificationLogRepositoryInterface
{
    protected function model(): string
    {
        return NotificationLog::class;
    }

    public function adminQuery(NotificationLogFilterData $filters): Builder
    {
        return $this->query()
            ->select('notification_logs.*')
            ->when($filters->event, fn (Builder $query, $event) => $query->where('event', $event))
            ->when($filters->status, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters->dateFrom, fn (Builder $query, CarbonInterface $from) => $query->where('created_at', '>=', $from->startOfDay()))
            ->when($filters->dateTo, fn (Builder $query, CarbonInterface $to) => $query->where('created_at', '<=', $to->endOfDay()))
            ->when($filters->search, fn (Builder $query, string $search) => $query->where(fn (Builder $inner) => $inner
                ->where('recipient', 'like', "%{$search}%")->orWhere('subject', 'like', "%{$search}%")));
    }

    public function dedupeKeyExists(string $key): bool
    {
        return $this->query()->where('dedupe_key', $key)->exists();
    }
}
