<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\Room\IcalFeedData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IcalFeedRequest;
use App\Models\Room;
use App\Models\RoomIcalFeed;
use App\Services\IcalService;
use Illuminate\Http\RedirectResponse;

/**
 * iCal export link and imported external calendars of a room (SRS 6.2), on the room page "Calendar sync" tab.
 */
class RoomIcalController extends Controller
{
    public function __construct(private readonly IcalService $ical) {}

    public function store(IcalFeedRequest $request, Room $room): RedirectResponse
    {
        $feed = $this->ical->addFeed($room, IcalFeedData::fromRequest($request));
        $result = $this->ical->sync($feed);

        return $this->back($room, $result['error'] === null
            ? ['success', "Calendar “{$feed->name}” added: {$result['added']} nights closed."]
            : ['error', "Calendar “{$feed->name}” added, but the first sync failed: {$result['error']}"]);
    }

    public function sync(Room $room, RoomIcalFeed $feed): RedirectResponse
    {
        abort_unless($feed->room_id === $room->id, 404);
        $result = $this->ical->sync($feed);

        return $this->back($room, $result['error'] === null
            ? ['success', "Synced: {$result['added']} nights closed, {$result['removed']} freed".($result['skipped'] > 0 ? ", {$result['skipped']} already taken" : '').'.']
            : ['error', 'Sync failed: '.$result['error']]);
    }

    public function destroy(Room $room, RoomIcalFeed $feed): RedirectResponse
    {
        abort_unless($feed->room_id === $room->id, 404);
        $this->ical->removeFeed($feed);

        return $this->back($room, ['success', 'External calendar removed and its nights freed.']);
    }

    public function regenerate(Room $room): RedirectResponse
    {
        $this->ical->regenerateToken($room);

        return $this->back($room, ['success', 'New export link created. Update it on the other channels.']);
    }

    /**
     * @param  array{0: string, 1: string}  $flash
     */
    private function back(Room $room, array $flash): RedirectResponse
    {
        return redirect()->to(route('admin.rooms.edit', $room).'#tab-ical')->with($flash[0], $flash[1]);
    }
}
