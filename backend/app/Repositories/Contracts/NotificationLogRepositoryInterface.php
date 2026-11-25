<?php

namespace App\Repositories\Contracts;

use App\DTOs\Notification\NotificationLogFilterData;
use App\Models\NotificationLog;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepositoryInterface<NotificationLog>
 */
interface NotificationLogRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return Builder<NotificationLog>
     */
    public function adminQuery(NotificationLogFilterData $filters): Builder;

    public function dedupeKeyExists(string $key): bool;
}
