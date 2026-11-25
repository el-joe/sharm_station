<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\NotificationLogDataTable;
use App\DTOs\Notification\NotificationLogFilterData;
use App\DTOs\Notification\NotificationTemplateData;
use App\Enums\ActivityAction;
use App\Enums\NotificationEvent;
use App\Enums\NotificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NotificationLogFilterRequest;
use App\Http\Requests\Admin\NotificationTemplateRequest;
use App\Models\NotificationLog;
use App\Services\ActivityLogger;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Notifications module (SRS 14.1): email templates per event and language (edit, preview, send test,
 * enable/disable) and the sending log with resend.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(): View
    {
        $stored = $this->notifications->templates()->groupBy(fn ($template): string => $template->event->value);

        return view('admin.notifications.index', [
            'events' => NotificationEvent::cases(),
            'locales' => TemplateRenderer::SUPPORTED_LOCALES,
            'stored' => $stored,
            'adminRecipients' => $this->notifications->adminRecipients(),
        ]);
    }

    public function edit(NotificationEvent $event, string $locale): View
    {
        abort_unless(in_array($locale, TemplateRenderer::SUPPORTED_LOCALES, true), 404);

        return view('admin.notifications.edit', [
            'event' => $event,
            'locale' => $locale,
            'template' => $this->notifications->template($event, $locale),
            'previewHtml' => $this->notifications->previewHtml($event, $locale),
        ]);
    }

    public function update(NotificationTemplateRequest $request, NotificationEvent $event, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, TemplateRenderer::SUPPORTED_LOCALES, true), 404);
        $this->notifications->saveTemplate($event, $locale, NotificationTemplateData::fromRequest($request));

        return redirect()->route('admin.notifications.edit', [$event, $locale])->with('success', 'Template saved.');
    }

    /**
     * Live preview of the unsaved form (subject + branded HTML), with sample values.
     */
    public function preview(NotificationTemplateRequest $request, NotificationEvent $event, string $locale): JsonResponse
    {
        abort_unless(in_array($locale, TemplateRenderer::SUPPORTED_LOCALES, true), 404);
        $data = NotificationTemplateData::fromRequest($request);

        return response()->json([
            'subject' => $this->notifications->preview($event, $locale, $data)->subject,
            'html' => $this->notifications->previewHtml($event, $locale, $data),
        ]);
    }

    public function sendTest(NotificationTemplateRequest $request, NotificationEvent $event, string $locale): RedirectResponse|JsonResponse
    {
        abort_unless(in_array($locale, TemplateRenderer::SUPPORTED_LOCALES, true), 404);
        $log = $this->notifications->sendTest($event, $locale, NotificationTemplateData::fromRequest($request), $request->user());
        $message = "Test email queued to {$log->recipient}.";

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->withInput()->with('success', $message);
    }

    public function log(): View
    {
        return view('admin.notifications.log', [
            'events' => NotificationEvent::options(),
            'statuses' => NotificationStatus::options(),
        ]);
    }

    public function logData(NotificationLogFilterRequest $request, NotificationLogDataTable $table): JsonResponse
    {
        return $table->json(NotificationLogFilterData::fromRequest($request));
    }

    public function resend(NotificationLog $log, ActivityLogger $activity): RedirectResponse
    {
        $copy = $this->notifications->resend($log);
        $activity->log(ActivityAction::NotificationResent, $log, "Email “{$log->subject}” resent to {$log->recipient}", ['new_log_id' => $copy?->id]);

        return back()->with('success', "Email queued again to {$log->recipient}.");
    }

    /**
     * Rendered email of the current template (opened in a new tab from the list).
     */
    public function show(NotificationEvent $event, string $locale): Response
    {
        abort_unless(in_array($locale, TemplateRenderer::SUPPORTED_LOCALES, true), 404);

        return response($this->notifications->previewHtml($event, $locale));
    }
}
