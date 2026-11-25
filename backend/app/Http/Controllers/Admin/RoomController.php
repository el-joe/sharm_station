<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\RoomDataTable;
use App\DTOs\Room\RoomData;
use App\Enums\RoomPricingMode;
use App\Enums\RoomStatus;
use App\Enums\RoomType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoomRequest;
use App\Models\Room;
use App\Services\BuildingService;
use App\Services\IcalService;
use App\Services\PriceMatrixService;
use App\Services\RoomService;
use App\Services\SeasonPriceService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function __construct(
        private readonly RoomService $rooms,
        private readonly BuildingService $buildings,
        private readonly SeasonPriceService $seasons,
        private readonly PriceMatrixService $matrix,
        private readonly SettingsService $settings,
        private readonly IcalService $ical,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.rooms.index', [
            'buildings' => $this->buildings->options(),
            'buildingId' => $request->integer('building_id') ?: null,
            'canPrice' => Gate::allows('manage-prices'),
        ]);
    }

    public function data(Request $request, RoomDataTable $table): JsonResponse
    {
        return $table->json($request->integer('building_id') ?: null, Gate::allows('manage-prices'));
    }

    public function create(Request $request): View
    {
        return view('admin.rooms.edit', $this->formData(new Room([
            'building_id' => $request->integer('building_id') ?: null,
            'room_type' => RoomType::Double,
            'status' => RoomStatus::Active,
            'pricing_mode' => RoomPricingMode::PerRoom,
            'max_adults' => 2,
            'max_children' => 0,
            'max_total' => 2,
            'min_nights' => 1,
        ])));
    }

    public function store(RoomRequest $request): RedirectResponse
    {
        $room = $this->rooms->create(RoomData::fromArray($request->payload()));

        return redirect()->route('admin.rooms.edit', $room)->with('success', 'Room created. You can now add its price list.');
    }

    public function edit(Room $room): View
    {
        return view('admin.rooms.edit', $this->formData($room->load('building')));
    }

    public function update(RoomRequest $request, Room $room): RedirectResponse
    {
        $this->rooms->update($room, RoomData::fromArray($request->payload()));

        return redirect()->route('admin.rooms.edit', $room)->with('success', 'Room saved.');
    }

    public function destroy(Room $room): RedirectResponse
    {
        $buildingId = $room->building_id;
        $this->rooms->delete($room);

        return redirect()->route('admin.rooms.index', ['building_id' => $buildingId])->with('success', 'Room deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Room $room): array
    {
        return [
            'room' => $room,
            'buildings' => $this->buildings->options(),
            'amenities' => BuildingController::AMENITIES,
            'matrix' => $room->exists ? $this->matrix->editorData($room) : null,
            'canPrice' => Gate::allows('manage-prices'),
            'commission' => $this->settings->previewCommission(),
            'icalUrl' => $room->exists ? $this->ical->exportUrl($room) : null,
            'icalFeeds' => $room->exists ? $room->icalFeeds()->get() : collect(),
        ];
    }
}
