<?php

namespace App\Services;

use App\DTOs\Room\RoomData;
use App\DTOs\Room\RoomFilterData;
use App\Enums\ActivityAction;
use App\Enums\RoomAvailabilityStatus;
use App\Enums\RoomUnavailableReason;
use App\Exceptions\DomainValidationException;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Repositories\Contracts\RoomRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class RoomService
{
    public function __construct(
        private readonly RoomRepositoryInterface $rooms,
        private readonly SeasonPriceService $seasons,
        private readonly CurrencyService $currencies,
        private readonly ActivityLogger $activity,
    ) {}

    public function paginate(RoomFilterData $filters): LengthAwarePaginator
    {
        $filters = $filters->withPriceBounds(
            $filters->priceMin !== null ? $this->currencies->toEur($filters->priceMin) : null,
            $filters->priceMax !== null ? $this->currencies->toEur($filters->priceMax) : null,
        );

        $page = $this->rooms->paginateBookable($filters);

        if ($filters->checksAvailability()) {
            foreach ($page->items() as $room) {
                $room->setAttribute('unavailable_reasons', array_map(
                    fn (RoomUnavailableReason $reason): string => $reason->value,
                    $room->unavailableReasons($filters->nights(), $filters->guests),
                ));
            }
        }

        return $page;
    }

    public function findBookable(int $id): ?Room
    {
        return $this->rooms->findBookable($id);
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Room>
     */
    public function findBookableMany(array $ids): Collection
    {
        return $this->rooms->findBookableMany($ids);
    }

    /**
     * @return Collection<int, Room>
     */
    public function siblings(Room $room, int $limit = 6): Collection
    {
        return $this->rooms->siblings($room, $limit);
    }

    /**
     * @return Collection<int, Room>
     */
    public function featured(int $limit = 6): Collection
    {
        return $this->rooms->featured($limit);
    }

    public function create(RoomData $data): Room
    {
        $room = $this->rooms->create($data->toAttributes());
        $this->seasons->refreshFromPrice($room);

        return $room;
    }

    public function update(Room $room, RoomData $data): Room
    {
        $room = $this->rooms->update($room, $data->toAttributes());
        $this->seasons->refreshFromPrice($room);

        return $room;
    }

    public function delete(Room $room): void
    {
        $this->rooms->delete($room);
    }

    /**
     * @return Builder<Room>
     */
    public function adminQuery(?int $buildingId = null): Builder
    {
        return $this->rooms->adminQuery($buildingId);
    }

    /**
     * Month grid for the availability calendar: weeks (Mon-Sun) of days with their status.
     *
     * @return list<list<array{date: string, day: int, in_month: bool, past: bool, status: RoomAvailabilityStatus, booking_id: int|null}>>
     */
    public function calendar(Room $room, CarbonImmutable $month): array
    {
        $first = $month->startOfMonth();
        $start = $first->startOfWeek(CarbonImmutable::MONDAY);
        $end = $first->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);
        $rows = $this->rooms->availabilityBetween($room, $start, $end);
        $today = now()->toDateString();
        $weeks = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            /** @var RoomAvailability|null $row */
            $row = $rows->get($day->toDateString());
            $status = $row === null ? RoomAvailabilityStatus::Open : ($row->blocksBooking() ? $row->status : RoomAvailabilityStatus::Open);

            $weeks[intdiv((int) $start->diffInDays($day), 7)][] = [
                'date' => $day->toDateString(),
                'day' => $day->day,
                'in_month' => $day->month === $first->month,
                'past' => $day->toDateString() < $today,
                'status' => $status,
                'booking_id' => $row?->booking_id,
            ];
        }

        return array_values($weeks);
    }

    /**
     * Occupancy grid of every room for one month (A1): rows grouped by building, one cell per night with
     * free / held / booked / blocked and the booking reference. Two queries, whatever the number of rooms.
     *
     * @return array{days: list<array{date: string, day: int, weekday: string, weekend: bool, today: bool, past: bool}>, buildings: list<array{id: int, name: string, rooms: list<array<string, mixed>>}>, totals: array<string, int>, holds: list<array<string, mixed>>}
     */
    public function occupancy(CarbonImmutable $month, ?int $buildingId = null): array
    {
        $first = $month->startOfMonth();
        $last = $first->endOfMonth()->startOfDay();
        $today = now()->toDateString();
        $rooms = $this->rooms->forOccupancy($buildingId);
        $rows = $this->rooms->availabilityForRooms($rooms->pluck('id')->all(), $first, $last)
            ->groupBy('room_id')
            ->map(fn ($days) => $days->keyBy(fn (RoomAvailability $day): string => $day->date->toDateString()));

        $days = [];

        for ($day = $first; $day->lte($last); $day = $day->addDay()) {
            $days[] = [
                'date' => $day->toDateString(),
                'day' => $day->day,
                'weekday' => $day->format('D'),
                'weekend' => $day->isWeekend(),
                'today' => $day->toDateString() === $today,
                'past' => $day->toDateString() < $today,
            ];
        }

        $totals = ['free' => 0, 'held' => 0, 'booked' => 0, 'blocked' => 0];
        $buildings = [];
        $holds = [];

        foreach ($rooms as $room) {
            $cells = [];

            foreach ($days as $day) {
                /** @var RoomAvailability|null $row */
                $row = $rows->get($room->id)?->get($day['date']);
                $status = match (true) {
                    $row === null, ! $row->blocksBooking() => 'free',
                    $row->status === RoomAvailabilityStatus::Booked => 'booked',
                    $row->status === RoomAvailabilityStatus::Held => 'held',
                    default => 'blocked',
                };
                $totals[$status]++;
                $booking = in_array($status, ['booked', 'held'], true) ? $row?->booking : null;
                $cells[] = [
                    'date' => $day['date'],
                    'status' => $status,
                    'imported' => $row?->room_ical_feed_id !== null,
                    'booking' => $booking !== null
                        ? ['id' => $booking->id, 'reference' => $booking->reference, 'lead_name' => $booking->lead_name, 'check_in' => $booking->check_in?->toDateString()]
                        : null,
                ];

                if ($status === 'held' && $booking !== null) {
                    $holds[$booking->id] ??= ['id' => $booking->id, 'reference' => $booking->reference, 'lead_name' => $booking->lead_name, 'held_until' => $row->held_until, 'rooms' => []];
                    $holds[$booking->id]['rooms'][$room->id] = (string) $room->name;
                }
            }

            $buildings[$room->building_id] ??= ['id' => $room->building_id, 'name' => (string) $room->building?->name, 'rooms' => []];
            $buildings[$room->building_id]['rooms'][] = [
                'id' => $room->id,
                'name' => (string) $room->name,
                'number' => $room->number,
                'inactive' => $room->status?->value === 'inactive',
                'cells' => $cells,
            ];
        }

        return ['days' => $days, 'buildings' => array_values($buildings), 'totals' => $totals, 'holds' => array_values($holds)];
    }

    public function findOrFail(int $id): Room
    {
        return $this->rooms->findOrFail($id);
    }

    /**
     * Manual blocks (maintenance, owner use). Only "open" and "closed" can be set by hand.
     *
     * @param  list<string>  $dates
     *
     * @throws DomainValidationException
     */
    public function setAvailability(Room $room, array $dates, RoomAvailabilityStatus $status): int
    {
        if (! in_array($status, [RoomAvailabilityStatus::Open, RoomAvailabilityStatus::Closed], true)) {
            throw DomainValidationException::withErrors(['status' => ['Only open and closed can be set manually.']]);
        }

        $changed = $this->rooms->setManualStatus($room, $dates, $status);

        if ($changed > 0) {
            $nights = collect($dates)->sort()->values();
            $range = $nights->first() === $nights->last() ? $nights->first() : $nights->first().' – '.$nights->last();
            $this->activity->log(
                ActivityAction::Updated,
                $room,
                'Room “'.$room->name.'”: '.$changed.' '.str('night')->plural($changed).' '.($status === RoomAvailabilityStatus::Closed ? 'blocked' : 'unblocked').' ('.$range.')',
                ['status' => $status->value, 'dates' => $nights->all(), 'changed' => $changed],
            );
        }

        return $changed;
    }
}
