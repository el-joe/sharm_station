<?php

namespace App\Repositories\Eloquent;

use App\Enums\RoomAvailabilityStatus;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\RoomIcalFeed;
use App\Repositories\Contracts\RoomIcalFeedRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<RoomIcalFeed>
 */
class RoomIcalFeedRepository extends BaseRepository implements RoomIcalFeedRepositoryInterface
{
    protected function model(): string
    {
        return RoomIcalFeed::class;
    }

    public function active(): Collection
    {
        return $this->query()->where('is_active', true)->with('room:id,name')->orderBy('id')->get();
    }

    public function syncNights(RoomIcalFeed $feed, array $dates, CarbonInterface $from): array
    {
        $from = $from->toDateString();
        $dates = array_values(array_unique(array_filter($dates, fn (string $date): bool => $date >= $from)));

        return DB::transaction(function () use ($feed, $dates, $from): array {
            $mine = RoomAvailability::query()->where('room_ical_feed_id', $feed->id)->where('date', '>=', $from)->lockForUpdate()->get();
            $stale = $mine->reject(fn (RoomAvailability $day): bool => in_array($day->date->toDateString(), $dates, true));
            $removed = $stale->count();

            foreach ($stale as $day) {
                $day->price_override !== null
                    ? $day->update(['status' => RoomAvailabilityStatus::Open, 'room_ical_feed_id' => null])
                    : $day->delete();
            }

            $existing = RoomAvailability::query()->where('room_id', $feed->room_id)->whereIn('date', $dates)->lockForUpdate()->get()
                ->keyBy(fn (RoomAvailability $day): string => $day->date->toDateString());
            $added = 0;
            $skipped = 0;

            foreach ($dates as $date) {
                /** @var RoomAvailability|null $day */
                $day = $existing->get($date);

                if ($day !== null && $day->room_ical_feed_id === $feed->id) {
                    continue;
                }

                if ($day !== null && ($day->status !== RoomAvailabilityStatus::Open && $day->blocksBooking())) {
                    $skipped++;

                    continue;
                }

                RoomAvailability::query()->updateOrCreate(
                    ['room_id' => $feed->room_id, 'date' => $date],
                    ['status' => RoomAvailabilityStatus::Closed, 'booking_id' => null, 'held_until' => null, 'room_ical_feed_id' => $feed->id],
                );
                $added++;
            }

            return ['added' => $added, 'removed' => $removed, 'skipped' => $skipped];
        });
    }

    public function exportableNights(Room $room, CarbonInterface $from, CarbonInterface $to): array
    {
        return Room::blockingDays($room->availability()->getQuery())
            ->whereNull('room_ical_feed_id')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->with('booking:id,reference')
            ->orderBy('date')
            ->get()
            ->map(fn (RoomAvailability $day): array => [
                'date' => $day->date->toDateString(),
                'status' => $day->status->value,
                'reference' => $day->booking?->reference,
            ])
            ->all();
    }
}
