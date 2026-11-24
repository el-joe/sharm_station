<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\ActivityLogDataTable;
use App\DTOs\Activity\ActivityFilterData;
use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivityFilterRequest;
use App\Services\ActivityLogger;
use App\Services\StaffUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Activity log (A7, super admins): who did what, on which record, with the changes, IP and time.
 */
class ActivityLogController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function index(StaffUserService $staff): View
    {
        return view('admin.activity.index', [
            'users' => $staff->adminQuery()->orderBy('name')->pluck('name', 'id')->all(),
            'actions' => ActivityAction::options(),
            'subjectTypes' => $this->activity->subjectTypeOptions(),
        ]);
    }

    public function data(ActivityFilterRequest $request, ActivityLogDataTable $table): JsonResponse
    {
        return $table->json(ActivityFilterData::fromRequest($request));
    }
}
