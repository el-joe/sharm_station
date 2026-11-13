<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\PartnerDataTable;
use App\Enums\PartnerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PartnerApproveRequest;
use App\Models\Partner;
use App\Services\PartnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Partner applications, approval and suspension (SRS 9.4).
 */
class PartnerController extends Controller
{
    public function __construct(private readonly PartnerService $partners) {}

    public function index(Request $request): View
    {
        return view('admin.partners.index', ['status' => PartnerStatus::tryFrom((string) $request->query('status'))]);
    }

    public function data(Request $request, PartnerDataTable $table): JsonResponse
    {
        return $table->json(PartnerStatus::tryFrom((string) $request->query('status')));
    }

    public function approve(PartnerApproveRequest $request, Partner $partner): RedirectResponse
    {
        $default = $request->validated('default_commission');
        $this->partners->approve($partner, $request->user(), $default !== null ? (float) $default : null);

        return back()->with('success', "{$partner->company} is now an active partner.");
    }

    public function suspend(Partner $partner): RedirectResponse
    {
        $this->partners->suspend($partner);

        return back()->with('success', "{$partner->company} has been suspended.");
    }
}
