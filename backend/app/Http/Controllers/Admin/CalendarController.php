<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoomAvailabilityStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CalendarBlockRequest;
use App\Services\BuildingService;
use App\Services\RoomService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Accommodation occupancy calendar (A1, SRS 12.3): rooms × nights for a month, manual blocks.
 */
class CalendarController extends Controller
{
    public function __construct(
        private readonly RoomService $rooms,
        private readonly BuildingService $buildings,
    ) {}

    public function index(Request $request): View
    {
        $month = rescue(fn () => CarbonImmutable::createFromFormat('!Y-m', (string) $request->query('month')), null, false)
            ?? CarbonImmutable::now()->startOfMonth();
        $buildingId = filled($request->query('building_id')) ? (int) $request->query('building_id') : null;

        return view('admin.calendar.index', [
            'month' => $month->startOfMonth(),
            'buildingId' => $buildingId,
            'buildings' => $this->buildings->options(),
            'grid' => $this->rooms->occupancy($month, $buildingId),
            'canManageBookings' => (bool) $request->user()?->role?->canManagePrices(),
        ]);
    }

    public function block(CalendarBlockRequest $request): JsonResponse
    {
        $status = $request->status();
        $dates = $request->dates();
        $changed = $this->rooms->setAvailability($this->rooms->findOrFail((int) $request->validated('room_id')), $dates, $status);

        return response()->json([
            'status' => $status === RoomAvailabilityStatus::Closed ? 'blocked' : 'free',
            'dates' => $dates,
            'changed' => $changed,
            'message' => match (true) {
                $changed === 0 => 'Nothing changed: booked or held nights cannot be blocked or freed.',
                $status === RoomAvailabilityStatus::Closed => $changed.' '.str('night')->plural($changed).' blocked.',
                default => $changed.' '.str('night')->plural($changed).' unblocked.',
            },
        ]);
    }
}
