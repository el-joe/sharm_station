<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\PartnerDataTable;
use App\DTOs\Commission\CommissionPercentsData;
use App\Enums\PartnerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PartnerApproveRequest;
use App\Http\Requests\Admin\PartnerCommissionRequest;
use App\Http\Requests\Admin\PartnerCouponsRequest;
use App\Http\Requests\Admin\PartnerRejectRequest;
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

    /**
     * "Coupons allowed" toggle (AJAX): whether coupon codes may be used on this partner's bookings.
     */
    public function updateCoupons(PartnerCouponsRequest $request, Partner $partner): JsonResponse
    {
        $partner = $this->partners->setCouponsAllowed($partner, $request->boolean('coupons_allowed'), $request->user());

        return response()->json([
            'coupons_allowed' => $partner->coupons_allowed,
            'message' => $partner->coupons_allowed ? "{$partner->company} can now use coupon codes." : "{$partner->company} can no longer use coupon codes.",
        ]);
    }

    public function suspend(Request $request, Partner $partner): RedirectResponse
    {
        $this->partners->suspend($partner, $request->user());

        return back()->with('success', "{$partner->company} has been suspended.");
    }

    public function reject(PartnerRejectRequest $request, Partner $partner): RedirectResponse
    {
        $this->partners->reject($partner, $request->validated('rejection_reason'), $request->user());

        return redirect()->route('admin.partners.show', $partner)->with('success', "The application of {$partner->company} has been rejected.");
    }
}
