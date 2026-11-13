<?php

namespace App\Services;

use App\DTOs\Room\RoomData;
use App\DTOs\Room\RoomFilterData;
use App\Enums\RoomAvailabilityStatus;
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
    ) {}

    public function paginate(RoomFilterData $filters): LengthAwarePaginator
    {
        $filters = $filters->withPriceBounds(
            $filters->priceMin !== null ? $this->currencies->toEur($filters->priceMin) : null,
            $filters->priceMax !== null ? $this->currencies->toEur($filters->priceMax) : null,
        );

        return $this->rooms->paginateBookable($filters);
    }

    public function findBookable(int $id): ?Room
    {
        return $this->rooms->findBookable($id);
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

        return $this->rooms->setManualStatus($room, $dates, $status);
    }
}
