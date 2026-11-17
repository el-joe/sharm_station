<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\Partner\CommissionMatrixData;
use App\Enums\CommissionCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommissionMatrixRequest;
use App\Services\CommissionService;
use App\Services\PartnerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Partners x commission types override matrix (CR-11).
 */
class CommissionMatrixController extends Controller
{
    public function __construct(
        private readonly PartnerService $partners,
        private readonly CommissionService $commissions,
    ) {}

    public function edit(): View
    {
        return view('admin.partners.commissions', [
            'partners' => $this->partners->commissionMatrix(),
            'categories' => CommissionCategory::cases(),
            'globals' => $this->commissions->globalPercents(),
        ]);
    }

    public function update(CommissionMatrixRequest $request): RedirectResponse
    {
        $count = $this->partners->saveCommissionMatrix(CommissionMatrixData::fromRequest($request));

        return redirect()->route('admin.partners.commissions.edit')->with('success', "Commissions saved for {$count} ".str('partner')->plural($count).'.');
    }
}
