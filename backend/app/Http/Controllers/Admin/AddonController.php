<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\AddonDataTable;
use App\DTOs\Addon\AddonData;
use App\Enums\AddonPricingBasis;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddonRequest;
use App\Models\Addon;
use App\Services\AddonService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AddonController extends Controller
{
    public function __construct(
        private readonly AddonService $addons,
        private readonly SettingsService $settings,
    ) {}

    public function index(): View
    {
        return view('admin.addons.index', ['canPrice' => Gate::allows('manage-prices')]);
    }

    public function data(AddonDataTable $table): JsonResponse
    {
        return $table->json(Gate::allows('manage-prices'));
    }

    public function create(): View
    {
        Gate::authorize('manage-prices');

        return view('admin.addons.form', [
            'addon' => new Addon(['is_active' => true, 'max_quantity' => 1, 'pricing_basis' => AddonPricingBasis::PerPerson]),
            'commission' => $this->settings->previewCommission(),
        ]);
    }

    public function store(AddonRequest $request): RedirectResponse
    {
        $this->addons->create(AddonData::fromRequest($request));

        return redirect()->route('admin.addons.index')->with('success', 'Add-on created.');
    }

    public function edit(Addon $addon): View
    {
        Gate::authorize('manage-prices');

        return view('admin.addons.form', ['addon' => $addon, 'commission' => $this->settings->previewCommission()]);
    }

    public function update(AddonRequest $request, Addon $addon): RedirectResponse
    {
        $this->addons->update($addon, AddonData::fromRequest($request));

        return redirect()->route('admin.addons.index')->with('success', 'Add-on saved.');
    }

    public function destroy(Addon $addon): RedirectResponse
    {
        Gate::authorize('manage-prices');
        $this->addons->delete($addon);

        return redirect()->route('admin.addons.index')->with('success', 'Add-on deleted.');
    }
}
