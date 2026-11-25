<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\Booking\CalendarMoveData;
use App\Enums\RoomAvailabilityStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CalendarBlockRequest;
use App\Http\Requests\Admin\CalendarMoveRequest;
use App\Services\BookingModificationService;
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

    /**
     * Drag and drop of a booking to another room and/or check-in (SRS 12.3). dry_run=1 returns the price diff to
     * confirm; the move itself is validated again on the server (availability, capacity, same building).
     */
    public function move(CalendarMoveRequest $request, BookingModificationService $modifications): JsonResponse
    {
        $move = CalendarMoveData::fromRequest($request);
        $result = $modifications->move($move, $request->user());
        $preview = $result['preview'];

        if (! $preview['valid']) {
            return response()->json(['message' => collect($preview['errors'])->flatten()->first(), 'errors' => $preview['errors'], 'diff' => $preview['diff']], 422);
        }

        return response()->json([
            'moved' => ! $move->dryRun,
            'diff' => $preview['diff'],
            'message' => $move->dryRun ? 'The move is possible.' : "Booking {$result['booking']->reference} moved.",
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
