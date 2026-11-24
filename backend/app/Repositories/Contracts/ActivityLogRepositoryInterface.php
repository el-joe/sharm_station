<?php

namespace App\Repositories\Contracts;

use App\DTOs\Activity\ActivityFilterData;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepositoryInterface<ActivityLog>
 */
interface ActivityLogRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return Builder<ActivityLog>
     */
    public function adminQuery(ActivityFilterData $filters): Builder;

    /**
     * Distinct subject types present in the log.
     *
     * @return list<string>
     */
    public function subjectTypes(): array;
}
