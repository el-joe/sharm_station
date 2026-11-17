<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\Commission\CommissionPercentsData;
use App\Enums\CommissionCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommissionSettingsRequest;
use App\Services\CommissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Settings → Commissions: global partner commission per type (CR-11).
 */
class CommissionSettingController extends Controller
{
    public function __construct(private readonly CommissionService $commissions) {}

    public function edit(): View
    {
        return view('admin.settings.commissions', [
            'categories' => CommissionCategory::cases(),
            'percents' => $this->commissions->globalPercents(),
            'overrides' => $this->commissions->partnersWithOverrides(),
        ]);
    }

    public function update(CommissionSettingsRequest $request): RedirectResponse
    {
        $this->commissions->saveGlobal(CommissionPercentsData::fromRequest($request));

        return redirect()->route('admin.commissions.edit')->with('success', 'Commission settings saved. They apply to new bookings.');
    }
}
