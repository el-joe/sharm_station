<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\PartnerDataTable;
use App\DTOs\Commission\CommissionPercentsData;
use App\Enums\PartnerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PartnerApproveRequest;
use App\Http\Requests\Admin\PartnerCommissionRequest;
use App\Models\Partner;
use App\Services\CommissionService;
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
    public function __construct(
        private readonly PartnerService $partners,
        private readonly CommissionService $commissions,
    ) {}

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
        $this->partners->approve($partner, $request->user());

        return back()->with('success', "{$partner->company} is now an active partner.");
    }

    /**
     * Partner profile with the Commissions tab (overrides per type, effective rate).
     */
    public function show(Partner $partner): View
    {
        return view('admin.partners.show', [
            'partner' => $partner->load(['user', 'categoryCommissions']),
            'commissions' => $this->commissions->effectiveFor($partner),
        ]);
    }

    public function updateCommissions(PartnerCommissionRequest $request, Partner $partner): RedirectResponse
    {
        $this->commissions->saveOverrides($partner, CommissionPercentsData::fromRequest($request));

        return redirect()->to(route('admin.partners.show', $partner).'#tab-commissions')->with('success', 'Commission overrides saved. They apply to new bookings.');
    }

    public function suspend(Partner $partner): RedirectResponse
    {
        $this->partners->suspend($partner);

        return back()->with('success', "{$partner->company} has been suspended.");
    }
}
