<?php

namespace App\DataTables;

use App\DataTables\Concerns\RendersRowActions;
use App\DTOs\Notification\NotificationLogFilterData;
use App\Models\NotificationLog;
use App\Services\Notifications\NotificationService;
use App\View\Components\Admin\StatusChip;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Yajra\DataTables\EloquentDataTable;

/**
 * Sending log (Notifications → Log): recipient, event, status with the error, related record and a resend button.
 */
class NotificationLogDataTable
{
    use RendersRowActions;

    private const RELATED_ROUTES = ['booking' => 'admin.bookings.show', 'partner' => 'admin.partners.show'];

    public function __construct(private readonly NotificationService $notifications) {}

    public function json(NotificationLogFilterData $filters): JsonResponse
    {
        return (new EloquentDataTable($this->notifications->logsQuery($filters)))
            ->editColumn('created_at', fn (NotificationLog $log): string => '<span class="whitespace-nowrap">'.e($log->created_at?->format('j M Y H:i')).'</span>'
                .($log->sent_at ? '<div class="text-xs text-ink-500">sent '.e($log->sent_at->format('H:i:s')).'</div>' : ''))
            ->editColumn('event', fn (NotificationLog $log): string => '<span class="chip bg-ink-100 text-ink-700">'.e($log->event->label()).'</span>'
                .'<div class="mt-0.5 text-xs text-ink-500 uppercase">'.e($log->locale).'</div>')
            ->editColumn('recipient', fn (NotificationLog $log): string => $this->titleCell($log->recipient, Str::limit($log->subject, 80)))
            ->editColumn('status', function (NotificationLog $log): string {
                $html = StatusChip::html($log->status);

                return $html.($log->error ? '<div class="mt-1 max-w-xs text-xs text-error">'.e(Str::limit($log->error, 160)).'</div>' : '')
                    .($log->resent_from_id ? '<div class="text-xs text-ink-500">resend of #'.e((string) $log->resent_from_id).'</div>' : '');
            })
            ->addColumn('related', fn (NotificationLog $log): string => $this->related($log))
            ->addColumn('actions', fn (NotificationLog $log): string => $this->actions(
                '<form method="POST" action="'.e(route('admin.notifications.log.resend', $log)).'" data-confirm="Send this email again to '.e($log->recipient).'?" data-confirm-ok="Resend" class="inline">'
                .'<input type="hidden" name="_token" value="'.e(csrf_token()).'"><button type="submit" class="btn-link">Resend</button></form>'
            ))
            ->rawColumns(['created_at', 'event', 'recipient', 'status', 'related', 'actions'])
            ->toJson();
    }

    private function related(NotificationLog $log): string
    {
        if ($log->related_type === null) {
            return '<span class="text-ink-500">—</span>';
        }

        $label = Str::headline($log->related_type).' #'.$log->related_id;
        $reference = $log->context['vars']['reference'] ?? null;
        $route = self::RELATED_ROUTES[$log->related_type] ?? null;
        $label = $reference !== null && $log->related_type === 'booking' ? $reference : $label;

        return $route !== null ? '<a href="'.e(route($route, $log->related_id)).'" class="btn-link whitespace-nowrap">'.e($label).'</a>' : '<span>'.e($label).'</span>';
    }
}
