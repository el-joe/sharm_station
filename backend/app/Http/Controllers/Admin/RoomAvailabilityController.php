<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoomAvailabilityStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoomAvailabilityRequest;
use App\Models\Room;
use App\Services\RoomService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Availability calendar per room: month grid, click to close/open dates (SRS 6.2).
 */
class RoomAvailabilityController extends Controller
{
    public function __construct(private readonly RoomService $rooms) {}

    public function show(Request $request, Room $room): View
    {
        $month = rescue(fn () => CarbonImmutable::createFromFormat('Y-m', (string) $request->query('month'))->startOfMonth(), null, false)
            ?? CarbonImmutable::now()->startOfMonth();

        return view('admin.rooms.calendar', [
            'room' => $room->load('building'),
            'month' => $month,
            'weeks' => $this->rooms->calendar($room, $month),
        ]);
    }

    public function update(RoomAvailabilityRequest $request, Room $room): JsonResponse
    {
        $status = $request->status();
        $changed = $this->rooms->setAvailability($room, $request->dates(), $status);

        return response()->json([
            'status' => $changed > 0 ? $status->value : ($status === RoomAvailabilityStatus::Open ? RoomAvailabilityStatus::Closed->value : RoomAvailabilityStatus::Open->value),
            'changed' => $changed,
            'message' => $changed > 0 ? 'Availability updated.' : 'Booked or held nights cannot be changed.',
        ]);
    }
}
