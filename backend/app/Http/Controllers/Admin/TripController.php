<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\TripDataTable;
use App\DTOs\Trip\TripAdminFilterData;
use App\DTOs\Trip\TripData;
use App\Enums\TripType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TripFilterRequest;
use App\Http\Requests\Admin\TripRequest;
use App\Models\Trip;
use App\Repositories\Contracts\AddonRepositoryInterface;
use App\Repositories\Contracts\DestinationRepositoryInterface;
use App\Services\PriceMatrixService;
use App\Services\SeasonPriceService;
use App\Services\SettingsService;
use App\Services\TripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Trips and Nile trips (one controller, scope "nile" switches the list).
 */
class TripController extends Controller
{
    public function __construct(
        private readonly TripService $trips,
        private readonly SeasonPriceService $seasons,
        private readonly PriceMatrixService $matrix,
        private readonly DestinationRepositoryInterface $destinations,
        private readonly AddonRepositoryInterface $addons,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.trips.index', [
            'nile' => $request->query('type') === TripType::Nile->value,
            'destinations' => $this->destinations->selectOptions(),
        ]);
    }

    public function data(TripFilterRequest $request, TripDataTable $table): JsonResponse
    {
        return $table->json(TripAdminFilterData::fromRequest($request), Gate::allows('manage-prices'));
    }

    public function create(Request $request): View
    {
        $trip = new Trip([
            'type' => $request->query('type') === TripType::Nile->value ? TripType::Nile : TripType::SingleDay,
            'private_enabled' => true,
            'deposit_percent' => $this->settings->defaultDepositPercent(),
            'min_persons' => 1,
            'days' => 1,
            'nights' => 0,
        ]);

        return view('admin.trips.edit', $this->formData($trip));
    }

    public function store(TripRequest $request): RedirectResponse
    {
        $trip = $this->trips->create(TripData::fromArray($request->tripPayload()));

        return redirect()->route('admin.trips.edit', $trip)->with('success', 'Trip created. You can now add its price list.');
    }

    public function edit(Trip $trip): View
    {
        return view('admin.trips.edit', $this->formData($this->trips->findForEditor($trip->id)));
    }

    public function update(TripRequest $request, Trip $trip): RedirectResponse
    {
        $this->trips->update($trip, TripData::fromArray($request->tripPayload()));

        return redirect()->to(route('admin.trips.edit', $trip).($request->input('_tab') ? '#'.$request->input('_tab') : ''))
            ->with('success', 'Trip saved.');
    }

    public function destroy(Trip $trip): RedirectResponse
    {
        $nile = $trip->type === TripType::Nile;
        $this->trips->delete($trip);

        return redirect()->route('admin.trips.index', $nile ? ['type' => 'nile'] : [])->with('success', 'Trip deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Trip $trip): array
    {
        return [
            'trip' => $trip,
            'destinations' => $this->destinations->selectOptions(),
            'addons' => $this->addons->selectOptions(),
            'matrix' => $trip->exists ? $this->matrix->editorData($trip) : null,
            'canPrice' => Gate::allows('manage-prices'),
            'commission' => $this->settings->previewCommission(),
        ];
    }
}
