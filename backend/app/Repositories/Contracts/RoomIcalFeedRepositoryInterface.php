<?php

namespace App\Repositories\Contracts;

use App\Models\Room;
use App\Models\RoomIcalFeed;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<RoomIcalFeed>
 */
interface RoomIcalFeedRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return Collection<int, RoomIcalFeed>
     */
    public function active(): Collection;

    /**
     * Replaces the nights imported from the feed: missing dates are freed, new dates closed unless the night is
     * already taken (booking, hold, manual block or another feed). Only dates from $from are touched.
     *
     * @param  list<string>  $dates  Y-m-d
     * @return array{added: int, removed: int, skipped: int}
     */
    public function syncNights(RoomIcalFeed $feed, array $dates, CarbonInterface $from): array;

    /**
     * Blocked nights of a room to export (booked, unexpired holds, manual closures), imported nights excluded.
     *
     * @return list<array{date: string, status: string, reference: string|null}>
     */
    public function exportableNights(Room $room, CarbonInterface $from, CarbonInterface $to): array;
}
