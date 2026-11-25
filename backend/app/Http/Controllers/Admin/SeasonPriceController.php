<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SeasonPricesRequest;
use App\Models\Package;
use App\Models\Room;
use App\Models\RoomOption;
use App\Models\SeasonPrice;
use App\Models\TransferFixedRoute;
use App\Models\Trip;
use App\Services\PriceMatrixService;
use App\Services\SeasonPriceService;
use Illuminate\Http\RedirectResponse;

/**
 * Price matrix (base per nationality group + seasons per group) of trips, packages, rooms and
 * transfer fixed routes (SRS 3.8, 3.9).
 */
class SeasonPriceController extends Controller
{
    public function __construct(
        private readonly SeasonPriceService $seasons,
        private readonly PriceMatrixService $matrix,
    ) {}

    public function updateTrip(SeasonPricesRequest $request, Trip $trip): RedirectResponse
    {
        $this->matrix->save($trip->load(['unitOptions', 'privateTiers']), $request->baseRows(), $request->rows());

        return redirect()->to(route('admin.trips.edit', $trip).'#tab-prices')->with('success', 'Price matrix saved.');
    }

    public function updateRoom(SeasonPricesRequest $request, Room $room): RedirectResponse
    {
        $this->matrix->save($room, $request->baseRows(), $request->rows());

        return redirect()->to(route('admin.rooms.edit', $room).'#tab-prices')->with('success', 'Price matrix saved.');
    }

    public function updatePackage(SeasonPricesRequest $request, Package $package): RedirectResponse
    {
        $this->matrix->save($package, $request->baseRows(), $request->rows());

        return redirect()->to(route('admin.packages.edit', $package).'#tab-prices')->with('success', 'Price matrix saved.');
    }

    public function updateFixedRoute(SeasonPricesRequest $request, TransferFixedRoute $fixedRoute): RedirectResponse
    {
        $this->matrix->save($fixedRoute, $request->baseRows(), $request->rows());

        return redirect()->to(route('admin.transfers.routes.edit', $fixedRoute).'#price-matrix')->with('success', 'Price matrix saved.');
    }

    public function updateRoomOption(SeasonPricesRequest $request, RoomOption $roomOption): RedirectResponse
    {
        $this->matrix->save($roomOption, $request->baseRows(), $request->rows());

        return redirect()->to(route('admin.room-options.edit', $roomOption).'#season-form')->with('success', 'Price matrix saved.');
    }

    public function copyToNextYear(SeasonPrice $season): RedirectResponse
    {
        $copy = $this->seasons->copyToNextYear($season);
        [$route, $anchor] = match (true) {
            $season->priceable instanceof Room => ['admin.rooms.edit', '#tab-prices'],
            $season->priceable instanceof Package => ['admin.packages.edit', '#tab-prices'],
            $season->priceable instanceof TransferFixedRoute => ['admin.transfers.routes.edit', '#price-matrix'],
            $season->priceable instanceof RoomOption => ['admin.room-options.edit', '#season-form'],
            default => ['admin.trips.edit', '#tab-prices'],
        };

        return redirect()->to(route($route, $season->priceable).$anchor)
            ->with('success', "Copied to {$this->seasons->formatRange($copy->starts_on, $copy->ends_on)}.");
    }
}
