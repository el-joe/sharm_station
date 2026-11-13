<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\PackageDataTable;
use App\DTOs\Package\PackageData;
use App\Enums\PackagePriceMode;
use App\Enums\PublishStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PackageRequest;
use App\Models\Package;
use App\Repositories\Contracts\AddonRepositoryInterface;
use App\Repositories\Contracts\DestinationRepositoryInterface;
use App\Repositories\Contracts\TripRepositoryInterface;
use App\Services\PackageService;
use App\Services\SeasonPriceService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Packages list and builder (SRS 4.2).
 */
class PackageController extends Controller
{
    public function __construct(
        private readonly PackageService $packages,
        private readonly SeasonPriceService $seasons,
        private readonly DestinationRepositoryInterface $destinations,
        private readonly TripRepositoryInterface $trips,
        private readonly AddonRepositoryInterface $addons,
        private readonly SettingsService $settings,
    ) {}

    public function index(): View
    {
        return view('admin.packages.index', ['canPrice' => Gate::allows('manage-prices')]);
    }

    public function data(Request $request, PackageDataTable $table): JsonResponse
    {
        $status = PublishStatus::tryFrom((string) $request->query('status'))?->value;

        return $table->json(Gate::allows('manage-prices'), $status);
    }

    public function create(): View
    {
        Gate::authorize('manage-prices');

        return view('admin.packages.edit', $this->formData(new Package([
            'status' => PublishStatus::Draft,
            'private_enabled' => true,
            'group_enabled' => true,
            'price_mode' => PackagePriceMode::Fixed,
            'min_persons' => 1,
            'days' => 1,
            'nights' => 0,
            'deposit_percent' => $this->settings->defaultDepositPercent(),
        ])));
    }

    public function store(PackageRequest $request): RedirectResponse
    {
        $package = $this->packages->create(PackageData::fromRequest($request));

        return redirect()->route('admin.packages.edit', $package)->with('success', 'Package created. You can now add its price list.');
    }

    public function edit(Package $package): View
    {
        Gate::authorize('manage-prices');

        return view('admin.packages.edit', $this->formData($this->packages->findForEditor($package->id)));
    }

    public function update(PackageRequest $request, Package $package): RedirectResponse
    {
        $this->packages->update($package, PackageData::fromRequest($request));

        return redirect()->to(route('admin.packages.edit', $package).($request->input('_tab') ? '#'.$request->input('_tab') : ''))
            ->with('success', 'Package saved.');
    }

    public function destroy(Package $package): RedirectResponse
    {
        Gate::authorize('manage-prices');
        $this->packages->delete($package);

        return redirect()->route('admin.packages.index')->with('success', 'Package deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Package $package): array
    {
        return [
            'package' => $package,
            'destinations' => $this->destinations->selectOptions(),
            'trips' => $this->trips->selectOptions('title'),
            'addons' => $this->addons->selectOptions(),
            'seasons' => $package->exists ? $this->seasons->allFor($package) : collect(),
            'commission' => $this->settings->previewCommission(),
        ];
    }
}
