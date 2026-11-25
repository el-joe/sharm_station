<?php

namespace App\Repositories\Contracts;

use App\DTOs\Room\RoomFilterData;
use App\Enums\RoomAvailabilityStatus;
use App\Models\Room;
use App\Models\RoomAvailability;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<Room>
 */
interface RoomRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Price bounds of $filters must already be in EUR.
     */
    public function paginateBookable(RoomFilterData $filters): LengthAwarePaginator;

    public function findBookable(int $id): ?Room;

    /**
     * @return Collection<int, Room>
     */
    public function featured(int $limit = 6): Collection;

    /**
     * @return Collection<int, Room>
     */
    public function siblings(Room $room, int $limit = 6): Collection;

    /**
     * Locks the room row for the rest of the transaction.
     */
    public function lockForBooking(int $id): ?Room;

    /**
     * Bookable rooms of a building free for the whole stay and fitting its length, with the stay's calendar rows.
     *
     * @return Collection<int, Room>
     */
    public function freeInBuilding(int $buildingId, CarbonInterface $checkIn, CarbonInterface $checkOut): Collection;

    /**
     * Bookable rooms by id with what the stay pricing needs.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Room>
     */
    public function findBookableMany(array $ids): Collection;

    /**
     * Bookable rooms locked for update (in id order, to avoid deadlocks) inside the booking transaction.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Room>
     */
    public function lockManyForBooking(array $ids): Collection;

    /**
     * @return Builder<Room>
     */
    public function adminQuery(?int $buildingId = null): Builder;

    /**
     * Room holds that expire within the given number of minutes.
     *
     * @return Collection<int, RoomAvailability>
     */
    public function holdsExpiringWithin(int $minutes): Collection;

    /**
     * Availability rows of a room between two dates (inclusive), keyed by Y-m-d.
     *
     * @return \Illuminate\Support\Collection<string, RoomAvailability>
     */
    public function availabilityBetween(Room $room, CarbonInterface $from, CarbonInterface $to): \Illuminate\Support\Collection;

    /**
     * Opens or closes dates manually; booked and active held nights are left untouched.
     *
     * @param  list<string>  $dates  Y-m-d
     * @return int Number of dates changed.
     */
    public function setManualStatus(Room $room, array $dates, RoomAvailabilityStatus $status): int;

    /**
     * Rooms for the occupancy grid, ordered by building and room, with their building.
     *
     * @return Collection<int, Room>
     */
    public function forOccupancy(?int $buildingId = null): Collection;

    /**
     * Availability rows of many rooms in a date range, with the booking reference (one query).
     *
     * @param  list<int>  $roomIds
     * @return \Illuminate\Support\Collection<int, RoomAvailability>
     */
    public function availabilityForRooms(array $roomIds, CarbonInterface $from, CarbonInterface $to): \Illuminate\Support\Collection;

    /**
     * Bookable rooms for the partner catalogue.
     *
     * @param  list<string>  $tokens
     * @return Collection<int, Room>
     */
    public function forCatalogue(?string $destination, array $tokens): Collection;
}
