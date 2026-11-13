<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\TransferDataTables;
use App\DTOs\Transfer\FixedRouteData;
use App\DTOs\Transfer\TransferHubData;
use App\DTOs\Transfer\TransferQuoteRequestData;
use App\DTOs\Transfer\VehicleTypeData;
use App\Enums\HubType;
use App\Enums\TransferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TransferFixedRouteRequest;
use App\Http\Requests\Admin\TransferHubRequest;
use App\Http\Requests\Admin\TransferRequestUpdateRequest;
use App\Http\Requests\Admin\TransferSimulatorRequest;
use App\Http\Requests\Admin\VehicleTypeRequest;
use App\Models\BookingIncludedTransfer;
use App\Models\TransferFixedRoute;
use App\Models\TransferHub;
use App\Models\TransferRequest;
use App\Models\VehicleType;
use App\Repositories\Contracts\DestinationRepositoryInterface;
use App\Services\Pricing\TransferPricingService;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Transfers back office (SRS 5): requests follow-up, hubs, vehicles, fixed routes and the pricing simulator.
 */
class TransferController extends Controller
{
    public function __construct(
        private readonly TransferService $transfers,
        private readonly TransferDataTables $tables,
        private readonly DestinationRepositoryInterface $destinations,
    ) {}

    public function requests(): View
    {
        return view('admin.transfers.requests', ['statuses' => TransferStatus::options()]);
    }

    public function requestsData(Request $request): JsonResponse
    {
        return $this->tables->requests(TransferStatus::tryFrom((string) $request->query('status'))?->value);
    }

    public function showRequest(TransferRequest $transferRequest): View
    {
        return view('admin.transfers.request-show', ['transfer' => $transferRequest->load(['booking', 'hub', 'vehicleType'])]);
    }

    public function updateRequest(TransferRequestUpdateRequest $request, TransferRequest $transferRequest): RedirectResponse
    {
        $this->transfers->updateRequest($transferRequest, $request->attributesForUpdate());

        return back()->with('success', 'Transfer request updated.');
    }

    public function updateIncludedTransfer(TransferRequestUpdateRequest $request, BookingIncludedTransfer $task): RedirectResponse
    {
        $attributes = $request->attributesForUpdate();
        $task->update([...collect($attributes)->only(['status', 'driver_name', 'driver_phone', 'car_plate'])->all(), 'notes' => $attributes['admin_notes'] ?? $task->notes]);

        return back()->with('success', 'Included transfer updated.');
    }

    public function hubs(): View
    {
        return view('admin.transfers.list', [
            'title' => 'Transfer hubs', 'subtitle' => 'Airports, ports and cities with their pricing settings.',
            'createUrl' => Gate::allows('manage-prices') ? route('admin.transfers.hubs.create') : null, 'createLabel' => 'New hub',
            'tableId' => 'hubs-table', 'dataUrl' => route('admin.transfers.hubs.data'),
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'title' => 'Hub'],
                ['data' => 'pricing', 'name' => 'pricing', 'title' => 'Pricing', 'orderable' => false, 'searchable' => false],
                ['data' => 'radius_km', 'name' => 'radius_km', 'title' => 'Radius', 'searchable' => false],
                ['data' => 'fixed_routes_count', 'name' => 'fixed_routes_count', 'title' => 'Fixed routes', 'searchable' => false],
                ['data' => 'is_enabled', 'name' => 'is_enabled', 'title' => 'Status', 'searchable' => false],
                ['data' => 'actions', 'name' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
            ],
        ]);
    }

    public function hubsData(): JsonResponse
    {
        return $this->tables->hubs();
    }

    public function createHub(): View
    {
        Gate::authorize('manage-prices');

        return $this->hubForm(new TransferHub([
            'type' => HubType::Airport, 'radius_km' => 50, 'is_enabled' => true, 'min_notice_hours' => 12, 'waiting_grace_minutes' => 30,
            'distance_tiers' => [['up_to_km' => 20, 'rate' => 1.2], ['up_to_km' => null, 'rate' => 0.8]],
        ]));
    }

    public function storeHub(TransferHubRequest $request): RedirectResponse
    {
        $this->transfers->saveHub(null, TransferHubData::fromRequest($request)->attributes);

        return redirect()->route('admin.transfers.hubs.index')->with('success', 'Hub created.');
    }

    public function editHub(TransferHub $hub): View
    {
        Gate::authorize('manage-prices');

        return $this->hubForm($hub);
    }

    public function updateHub(TransferHubRequest $request, TransferHub $hub): RedirectResponse
    {
        $this->transfers->saveHub($hub, TransferHubData::fromRequest($request)->attributes);

        return redirect()->route('admin.transfers.hubs.index')->with('success', 'Hub saved.');
    }

    public function destroyHub(TransferHub $hub): RedirectResponse
    {
        Gate::authorize('manage-prices');
        $this->transfers->deleteHub($hub);

        return redirect()->route('admin.transfers.hubs.index')->with('success', 'Hub deleted.');
    }

    public function vehicles(): View
    {
        return view('admin.transfers.list', [
            'title' => 'Vehicle types', 'subtitle' => 'Capacity, luggage and the price multiplier V.',
            'createUrl' => Gate::allows('manage-prices') ? route('admin.transfers.vehicles.create') : null, 'createLabel' => 'New vehicle',
            'tableId' => 'vehicles-table', 'dataUrl' => route('admin.transfers.vehicles.data'),
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'title' => 'Vehicle'],
                ['data' => 'capacity', 'name' => 'capacity', 'title' => 'Passengers', 'searchable' => false],
                ['data' => 'luggage', 'name' => 'luggage', 'title' => 'Luggage', 'searchable' => false],
                ['data' => 'multiplier', 'name' => 'multiplier', 'title' => 'Multiplier', 'searchable' => false],
                ['data' => 'is_enabled', 'name' => 'is_enabled', 'title' => 'Status', 'searchable' => false],
                ['data' => 'actions', 'name' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
            ],
        ]);
    }

    public function vehiclesData(): JsonResponse
    {
        return $this->tables->vehicles();
    }

    public function createVehicle(): View
    {
        Gate::authorize('manage-prices');

        return view('admin.transfers.vehicle-form', ['vehicle' => new VehicleType(['multiplier' => 1, 'capacity' => 3, 'luggage' => 3, 'is_enabled' => true])]);
    }

    public function storeVehicle(VehicleTypeRequest $request): RedirectResponse
    {
        $this->transfers->saveVehicle(null, VehicleTypeData::fromRequest($request)->attributes);

        return redirect()->route('admin.transfers.vehicles.index')->with('success', 'Vehicle created.');
    }

    public function editVehicle(VehicleType $vehicle): View
    {
        Gate::authorize('manage-prices');

        return view('admin.transfers.vehicle-form', ['vehicle' => $vehicle]);
    }

    public function updateVehicle(VehicleTypeRequest $request, VehicleType $vehicle): RedirectResponse
    {
        $this->transfers->saveVehicle($vehicle, VehicleTypeData::fromRequest($request)->attributes);

        return redirect()->route('admin.transfers.vehicles.index')->with('success', 'Vehicle saved.');
    }

    public function destroyVehicle(VehicleType $vehicle): RedirectResponse
    {
        Gate::authorize('manage-prices');
        $this->transfers->deleteVehicle($vehicle);

        return redirect()->route('admin.transfers.vehicles.index')->with('success', 'Vehicle deleted.');
    }

    public function routes(): View
    {
        return view('admin.transfers.list', [
            'title' => 'Fixed routes', 'subtitle' => 'A fixed price for popular destinations overrides the distance formula.',
            'createUrl' => Gate::allows('manage-prices') ? route('admin.transfers.routes.create') : null, 'createLabel' => 'New fixed route',
            'tableId' => 'routes-table', 'dataUrl' => route('admin.transfers.routes.data'),
            'columns' => [
                ['data' => 'destination', 'name' => 'destination', 'title' => 'Destination'],
                ['data' => 'hub', 'name' => 'hub', 'title' => 'Hub', 'orderable' => false, 'searchable' => false],
                ['data' => 'vehicle', 'name' => 'vehicle', 'title' => 'Vehicle', 'orderable' => false, 'searchable' => false],
                ['data' => 'price', 'name' => 'price', 'title' => 'Price', 'searchable' => false, 'className' => 'text-right tabular-nums'],
                ['data' => 'partner_price', 'name' => 'partner_price', 'title' => 'Partner', 'searchable' => false, 'className' => 'text-right tabular-nums'],
                ['data' => 'is_enabled', 'name' => 'is_enabled', 'title' => 'Status', 'searchable' => false],
                ['data' => 'actions', 'name' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
            ],
        ]);
    }

    public function routesData(): JsonResponse
    {
        return $this->tables->routes();
    }

    public function createRoute(): View
    {
        Gate::authorize('manage-prices');

        return $this->routeForm(new TransferFixedRoute(['match_radius_km' => 1, 'is_enabled' => true]));
    }

    public function storeRoute(TransferFixedRouteRequest $request): RedirectResponse
    {
        $this->transfers->saveFixedRoute(null, FixedRouteData::fromRequest($request)->attributes);

        return redirect()->route('admin.transfers.routes.index')->with('success', 'Fixed route created.');
    }

    public function editRoute(TransferFixedRoute $fixedRoute): View
    {
        Gate::authorize('manage-prices');

        return $this->routeForm($fixedRoute);
    }

    public function updateRoute(TransferFixedRouteRequest $request, TransferFixedRoute $fixedRoute): RedirectResponse
    {
        $this->transfers->saveFixedRoute($fixedRoute, FixedRouteData::fromRequest($request)->attributes);

        return redirect()->route('admin.transfers.routes.index')->with('success', 'Fixed route saved.');
    }

    public function destroyRoute(TransferFixedRoute $fixedRoute): RedirectResponse
    {
        Gate::authorize('manage-prices');
        $this->transfers->deleteFixedRoute($fixedRoute);

        return redirect()->route('admin.transfers.routes.index')->with('success', 'Fixed route deleted.');
    }

    /**
     * Pricing simulator: GET shows the form, a filled form (query string) shows the itemized result.
     */
    public function simulator(TransferSimulatorRequest $request, TransferPricingService $pricing): View
    {
        $quote = $request->filled('hub_id')
            ? $pricing->quote(TransferQuoteRequestData::fromArray($request->validated()))
            : null;

        return view('admin.transfers.simulator', [
            'hubs' => $this->transfers->hubOptions(),
            'vehicles' => $this->transfers->vehicleOptions(),
            'quote' => $quote,
        ]);
    }

    private function hubForm(TransferHub $hub): View
    {
        return view('admin.transfers.hub-form', [
            'hub' => $hub,
            'destinations' => $this->destinations->selectOptions(),
            'vehicles' => $this->transfers->vehicleOptions(),
        ]);
    }

    private function routeForm(TransferFixedRoute $route): View
    {
        return view('admin.transfers.route-form', [
            'route' => $route,
            'hubs' => $this->transfers->hubOptions(),
            'vehicles' => $this->transfers->vehicleOptions(),
        ]);
    }
}
