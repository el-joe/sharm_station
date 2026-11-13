<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\ContactService;
use App\View\Components\Admin\StatusChip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\EloquentDataTable;

/**
 * Messages sent from the site contact form.
 */
class ContactMessageController extends Controller
{
    public function __construct(private readonly ContactService $contact) {}

    public function index(): View
    {
        return view('admin.contact-messages.index');
    }

    public function data(Request $request): JsonResponse
    {
        $status = in_array($request->query('status'), ['new', 'read'], true) ? $request->query('status') : null;

        return (new EloquentDataTable($this->contact->adminQuery($status)))
            ->editColumn('name', fn (ContactMessage $message): string => '<a href="'.e(route('admin.contact-messages.show', $message)).'" class="font-semibold hover:underline">'.e($message->name).'</a><div class="text-xs text-ink-500">'.e($message->email.($message->phone ? ' · '.$message->phone : '')).'</div>')
            ->editColumn('subject', fn (ContactMessage $message): string => e($message->subject->label().($message->booking_reference ? ' · '.$message->booking_reference : '')))
            ->editColumn('message', fn (ContactMessage $message): string => e(str($message->message)->limit(90)))
            ->editColumn('status', fn (ContactMessage $message): string => StatusChip::html($message->status, null, $message->status === 'new' ? 'warning' : 'neutral'))
            ->editColumn('created_at', fn (ContactMessage $message): string => e($message->created_at->format('j M Y H:i')))
            ->filterColumn('name', fn (Builder $query, string $keyword) => $query->where(fn (Builder $query) => $query->where('name', 'like', "%{$keyword}%")->orWhere('email', 'like', "%{$keyword}%")))
            ->rawColumns(['name', 'subject', 'message', 'status', 'created_at'])
            ->toJson();
    }

    public function show(ContactMessage $contactMessage): View
    {
        return view('admin.contact-messages.show', ['message' => $this->contact->markRead($contactMessage)]);
    }
}
