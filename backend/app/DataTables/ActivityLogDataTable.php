<?php

namespace App\DataTables;

use App\DataTables\Concerns\RendersRowActions;
use App\DTOs\Activity\ActivityFilterData;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Yajra\DataTables\EloquentDataTable;

/**
 * Activity log list; filters and the free-text search (description) are applied by ActivityFilterData.
 */
class ActivityLogDataTable
{
    use RendersRowActions;

    /**
     * Subject type => admin route of its page.
     *
     * @var array<string, string>
     */
    private const SUBJECT_ROUTES = [
        'booking' => 'admin.bookings.show',
        'partner' => 'admin.partners.show',
        'trip' => 'admin.trips.edit',
        'package' => 'admin.packages.edit',
        'building' => 'admin.buildings.edit',
        'room' => 'admin.rooms.edit',
        'addon' => 'admin.addons.edit',
        'destination' => 'admin.destinations.edit',
        'coupon' => 'admin.coupons.edit',
        'blog_post' => 'admin.content.posts.edit',
        'faq_item' => 'admin.content.faq.items.edit',
        'payment_proof' => 'admin.receipts.show',
        'ai_suggestion' => 'admin.ai-suggestions.show',
    ];

    public function __construct(private readonly ActivityLogger $activity) {}

    public function json(ActivityFilterData $filters): JsonResponse
    {
        return (new EloquentDataTable($this->activity->adminQuery($filters)))
            ->editColumn('created_at', fn (ActivityLog $log): string => '<span class="whitespace-nowrap">'.e($log->created_at?->format('j M Y H:i')).'</span>')
            ->addColumn('causer', fn (ActivityLog $log): string => $this->titleCell($log->causer?->name ?? 'Deleted user', $log->causer?->email))
            ->editColumn('action', fn (ActivityLog $log): string => '<span class="chip bg-ink-100 text-ink-700">'.e($log->action->label()).'</span>')
            ->editColumn('description', fn (ActivityLog $log): string => $this->describe($log))
            ->addColumn('subject', fn (ActivityLog $log): string => $this->subjectLink($log))
            ->addColumn('origin', fn (ActivityLog $log): string => '<span class="text-xs text-ink-500" title="'.e((string) $log->user_agent).'">'.e($log->ip_address ?? '—').'</span>')
            ->rawColumns(['created_at', 'causer', 'action', 'description', 'subject', 'origin'])
            ->toJson();
    }

    private function describe(ActivityLog $log): string
    {
        $html = '<div class="max-w-xl">'.e($log->description);
        $changes = $log->properties['changes'] ?? null;

        if (is_array($changes) && isset($changes['new'])) {
            $items = collect((array) $changes['new'])->take(8)->map(function ($new, $key) use ($changes): string {
                $old = $changes['old'][$key] ?? null;
                $format = fn ($value): string => Str::limit(is_scalar($value) || $value === null ? var_export($value, true) : (string) json_encode($value, JSON_UNESCAPED_UNICODE), 60);

                return '<li><span class="font-medium">'.e((string) $key).'</span>: '.e($format($old)).' → '.e($format($new)).'</li>';
            })->implode('');
            $html .= '<details class="mt-1 text-xs text-ink-700"><summary class="cursor-pointer text-primary-700">Changes</summary><ul class="mt-1 space-y-0.5">'.$items.'</ul></details>';
        } elseif (! empty($log->properties) && ! isset($log->properties['attributes'])) {
            $html .= '<div class="mt-0.5 text-xs text-ink-500">'.e(Str::limit((string) json_encode($log->properties, JSON_UNESCAPED_UNICODE), 160)).'</div>';
        }

        return $html.'</div>';
    }

    private function subjectLink(ActivityLog $log): string
    {
        if ($log->subject_type === null) {
            return '<span class="text-ink-500">—</span>';
        }

        $label = Str::headline($log->subject_type).' #'.$log->subject_id;
        $route = self::SUBJECT_ROUTES[$log->subject_type] ?? null;

        if ($log->subject_type === 'user') {
            $user = User::query()->find($log->subject_id);
            $route = $user?->isStaff() ? 'admin.users.edit' : ($user !== null ? 'admin.customers.show' : null);
        }

        return $route !== null && $log->subject_id !== null && Route::has($route)
            ? '<a href="'.e(route($route, $log->subject_id)).'" class="btn-link whitespace-nowrap">'.e($label).'</a>'
            : '<span class="whitespace-nowrap">'.e($label).'</span>';
    }
}
