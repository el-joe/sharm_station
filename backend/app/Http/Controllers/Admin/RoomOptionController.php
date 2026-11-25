<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\Room\RoomOptionData;
use App\Enums\RoomPricePeriod;
use App\Enums\RoomPricingMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoomOptionRequest;
use App\Models\Package;
use App\Models\RoomOption;
use App\Models\Trip;
use App\Services\PriceMatrixService;
use App\Services\RoomOptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Optional rooms of multi-day trips, Nile trips (cabins) and package stages (SRS 7.1), with their price matrix.
 */
class RoomOptionController extends Controller
{
    public function __construct(
        private readonly RoomOptionService $options,
        private readonly PriceMatrixService $matrix,
    ) {}

    public function trip(Trip $trip): View
    {
        return $this->index($trip);
    }

    public function package(Package $package): View
    {
        return $this->index($package->load('stages.destination'));
    }

    public function storeForTrip(RoomOptionRequest $request, Trip $trip): RedirectResponse
    {
        return $this->store($request, $trip);
    }

    public function storeForPackage(RoomOptionRequest $request, Package $package): RedirectResponse
    {
        return $this->store($request, $package);
    }

    public function edit(RoomOption $roomOption): View
    {
        $owner = $roomOption->owner;

        return view('admin.room-options.edit', [
            'option' => $roomOption,
            'owner' => $owner instanceof Package ? $owner->load('stages.destination') : $owner,
            'indexUrl' => $this->indexUrl($owner),
            'matrix' => $this->matrix->editorData($roomOption),
        ]);
    }

    public function update(RoomOptionRequest $request, RoomOption $roomOption): RedirectResponse
    {
        $this->options->update($roomOption, RoomOptionData::fromRequest($request));

        return redirect()->route('admin.room-options.edit', $roomOption)->with('success', 'Room saved.');
    }

    public function destroy(RoomOption $roomOption): RedirectResponse
    {
        $url = $this->indexUrl($roomOption->owner);
        $this->options->delete($roomOption);

        return redirect()->to($url)->with('success', 'Room removed.');
    }

    private function index(Trip|Package $owner): View
    {
        return view('admin.room-options.index', [
            'owner' => $owner,
            'supported' => $this->options->supports($owner),
            'options' => $this->options->forOwner($owner),
            'storeUrl' => $owner instanceof Package ? route('admin.packages.room-options.store', $owner) : route('admin.trips.room-options.store', $owner),
            'editUrl' => $owner instanceof Package ? route('admin.packages.edit', $owner) : route('admin.trips.edit', $owner),
            'blank' => new RoomOption(['pricing_mode' => RoomPricingMode::PerRoom, 'price_period' => RoomPricePeriod::PerNight, 'max_adults' => 2, 'max_children' => 1, 'max_total' => 3, 'max_quantity' => 10, 'is_active' => true]),
        ]);
    }

    private function store(RoomOptionRequest $request, Trip|Package $owner): RedirectResponse
    {
        abort_unless($this->options->supports($owner), 422, 'Rooms can only be added to multi-day products.');
        $option = $this->options->create($owner, RoomOptionData::fromRequest($request));

        return redirect()->route('admin.room-options.edit', $option)->with('success', 'Room added. You can now set its prices per nationality group and season.');
    }

    private function indexUrl(mixed $owner): string
    {
        return $owner instanceof Package ? route('admin.packages.room-options', $owner) : route('admin.trips.room-options', $owner);
    }
}
