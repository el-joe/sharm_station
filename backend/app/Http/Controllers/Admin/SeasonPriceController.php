<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SeasonPricesRequest;
use App\Models\Package;
use App\Models\Room;
use App\Models\SeasonPrice;
use App\Models\Trip;
use App\Services\SeasonPriceService;
use Illuminate\Http\RedirectResponse;

/**
 * Price list (seasons) of trips, packages and rooms (SRS 3.8).
 */
class SeasonPriceController extends Controller
{
    public function __construct(private readonly SeasonPriceService $seasons) {}

    public function updateTrip(SeasonPricesRequest $request, Trip $trip): RedirectResponse
    {
        $this->seasons->sync($trip, $request->rows());

        return redirect()->to(route('admin.trips.edit', $trip).'#tab-prices')->with('success', 'Price list saved.');
    }

    public function updateRoom(SeasonPricesRequest $request, Room $room): RedirectResponse
    {
        $this->seasons->sync($room, $request->rows());

        return redirect()->to(route('admin.rooms.edit', $room).'#tab-prices')->with('success', 'Price list saved.');
    }

    public function updatePackage(SeasonPricesRequest $request, Package $package): RedirectResponse
    {
        $this->seasons->sync($package, $request->rows());

        return redirect()->to(route('admin.packages.edit', $package).'#tab-prices')->with('success', 'Price list saved.');
    }

    public function copyToNextYear(SeasonPrice $season): RedirectResponse
    {
        $copy = $this->seasons->copyToNextYear($season);
        $route = match (true) {
            $season->priceable instanceof Room => 'admin.rooms.edit',
            $season->priceable instanceof Package => 'admin.packages.edit',
            default => 'admin.trips.edit',
        };

        return redirect()->to(route($route, $season->priceable).'#tab-prices')
            ->with('success', "Copied to {$this->seasons->formatRange($copy->starts_on, $copy->ends_on)}.");
    }
}
