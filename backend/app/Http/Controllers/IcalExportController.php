<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Services\IcalService;
use Illuminate\Http\Response;

/**
 * Public iCal feed of a room's blocked nights, protected by the room's secret token (SRS 6.2).
 */
class IcalExportController extends Controller
{
    public function __invoke(Room $room, string $token, IcalService $ical): Response
    {
        abort_unless($ical->tokenMatches($room, $token), 404);

        return response($ical->export($room->loadMissing('building')), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="room-'.$room->id.'.ics"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
