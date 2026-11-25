<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Room\RoomFilterData;
use App\Enums\RoomAvailabilityStatus;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Repositories\Eloquent\Concerns\MatchesSearchTokens;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<Room>
 */
class RoomRepository extends BaseRepository implements RoomRepositoryInterface
{
    use MatchesSearchTokens;

    protected function model(): string
    {
        return Room::class;
    }

    public function paginateBookable(RoomFilterData $filters): LengthAwarePaginator
    {
        $query = $this->query()
            ->bookable()
            ->select('rooms.*')
            ->with(['building.destination', 'seasonPrices' => fn ($query) => $query->where('is_active', true), 'productPrices'])
            ->when($filters->destination, fn (Builder $query, string $slug) => $query->whereHas('building.destination', fn (Builder $destination) => $destination->where('slug', $slug)))
            ->when($filters->building, fn (Builder $query, string $slug) => $query->whereHas('building', fn (Builder $building) => $building->where('slug', $slug)))
            ->when($filters->roomType, fn (Builder $query, $type) => $query->where('room_type', $type))
            ->when($filters->guests && $filters->onlyAvailable, fn (Builder $query) => $query->whereRaw('max_total + max_extra_beds >= ?', [$filters->guests]))
            ->when($filters->priceMin !== null, fn (Builder $query) => $query->where('from_price', '>=', $filters->priceMin))
            ->when($filters->priceMax !== null, fn (Builder $query) => $query->where('from_price', '<=', $filters->priceMax))
            ->when($filters->stars, fn (Builder $query, int $stars) => $query->whereHas('building', fn (Builder $building) => $building->where('stars', '>=', $stars)))
            ->when($filters->freeCancellation, fn (Builder $query) => $query->whereHas('building', fn (Builder $building) => $building->whereNotNull('free_cancellation_hours')))
            ->when($filters->nights() !== null && $filters->onlyAvailable, fn (Builder $query) => $query
                ->where('min_nights', '<=', $filters->nights())
                ->where(fn (Builder $query) => $query->whereNull('max_nights')->orWhere('max_nights', '>=', $filters->nights()))
                ->availableBetween($filters->checkIn, $filters->checkOut))
            ->when($filters->nights() !== null, fn (Builder $query) => $query->withStayAvailability($filters->checkIn, $filters->checkOut))
            ->withAvailabilitySummary();

        if ($filters->groupBy === 'building') {
            $query->orderBy('building_id');
        }

        if ($filters->checksAvailability() && ! $filters->onlyAvailable) {
            // Bookable rooms first, the unavailable ones after them (greyed with their reason, SRS 6.3).
            $nights = $filters->nights();
            $conditions = [];
            $bindings = [];

            if ($nights !== null) {
                $conditions[] = 'stay_booked = 1 OR stay_closed = 1 OR rooms.min_nights > ? OR (rooms.max_nights IS NOT NULL AND rooms.max_nights < ?)';
                array_push($bindings, $nights, $nights);
            }

            if ($filters->guests !== null) {
                $conditions[] = 'rooms.max_total + rooms.max_extra_beds < ?';
                $bindings[] = $filters->guests;
            }

            $query->orderByRaw('('.implode(' OR ', $conditions).') ASC', $bindings);
        }

        match ($filters->sort) {
            'price_asc' => $query->orderBy('from_price'),
            'price_desc' => $query->orderByDesc('from_price'),
            default => $query->orderBy('building_id')->orderBy('sort'),
        };

        return $query->paginate($filters->perPage)->withQueryString();
    }

    public function findBookable(int $id): ?Room
    {
        return $this->query()
            ->bookable()
            ->with(['building.destination', 'building.childPolicy.bands', 'childPolicy.bands', 'seasonPrices' => fn ($query) => $query->where('is_active', true), 'productPrices'])
            ->withAvailabilitySummary()
            ->find($id);
    }

    public function featured(int $limit = 6): Collection
    {
        return $this->query()
            ->bookable()
            ->with(['building.destination', 'seasonPrices', 'productPrices'])
            ->withAvailabilitySummary()
            ->orderBy('from_price')
            ->limit($limit)
            ->get();
    }

    public function siblings(Room $room, int $limit = 6): Collection
    {
        return $this->query()
            ->bookable()
            ->where('building_id', $room->building_id)
            ->whereKeyNot($room->getKey())
            ->with(['building.destination', 'seasonPrices', 'productPrices'])
            ->withAvailabilitySummary()
            ->orderBy('sort')
            ->limit($limit)
            ->get();
    }

    public function lockForBooking(int $id): ?Room
    {
        return $this->query()->bookable()->lockForUpdate()->find($id);
    }

    public function freeInBuilding(int $buildingId, CarbonInterface $checkIn, CarbonInterface $checkOut): Collection
    {
        $nights = (int) $checkIn->diffInDays($checkOut);

        return $this->query()
            ->bookable()
            ->where('building_id', $buildingId)
            ->where('min_nights', '<=', $nights)
            ->where(fn (Builder $query) => $query->whereNull('max_nights')->orWhere('max_nights', '>=', $nights))
            ->availableBetween($checkIn, $checkOut)
            ->with([
                'building.childPolicy.bands', 'childPolicy.bands', 'seasonPrices' => fn ($query) => $query->where('is_active', true), 'productPrices',
                'availability' => fn ($query) => $query->whereBetween('date', [$checkIn->toDateString(), $checkOut->copy()->subDay()->toDateString()]),
            ])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    public function findBookableMany(array $ids): Collection
    {
        return $this->query()
            ->bookable()
            ->with(['building.destination', 'building.childPolicy.bands', 'childPolicy.bands', 'seasonPrices' => fn ($query) => $query->where('is_active', true), 'productPrices'])
            ->whereKey($ids)
            ->get();
    }

    public function lockManyForBooking(array $ids): Collection
    {
        return $this->query()->bookable()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
    }

    public function adminQuery(?int $buildingId = null): Builder
    {
        return $this->query()
            ->select('rooms.*')
            ->with('building:id,name')
            ->when($buildingId, fn (Builder $query, int $id) => $query->where('building_id', $id));
    }

    public function holdsExpiringWithin(int $minutes): Collection
    {
        return RoomAvailability::query()
            ->with('room.building:id,name')
            ->where('status', RoomAvailabilityStatus::Held)
            ->whereBetween('held_until', [now(), now()->addMinutes($minutes)])
            ->orderBy('held_until')
            ->get();
    }

    public function availabilityBetween(Room $room, CarbonInterface $from, CarbonInterface $to): \Illuminate\Support\Collection
    {
        return $room->availability()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn (RoomAvailability $day): string => $day->date->toDateString());
    }

    public function forOccupancy(?int $buildingId = null): Collection
    {
        return $this->query()
            ->select(['rooms.id', 'rooms.building_id', 'rooms.name', 'rooms.number', 'rooms.status', 'rooms.sort'])
            ->with('building:id,name')
            ->when($buildingId, fn (Builder $query, int $id) => $query->where('rooms.building_id', $id))
            ->join('buildings', 'buildings.id', '=', 'rooms.building_id')
            ->orderBy('buildings.name->en')
            ->orderBy('rooms.sort')
            ->orderBy('rooms.id')
            ->get();
    }

    public function availabilityForRooms(array $roomIds, CarbonInterface $from, CarbonInterface $to): \Illuminate\Support\Collection
    {
        if ($roomIds === []) {
            return collect();
        }

        return RoomAvailability::query()
            ->select(['id', 'room_id', 'date', 'status', 'booking_id', 'room_ical_feed_id', 'held_until'])
            ->with('booking:id,reference,lead_name,check_in,check_out,status,payment_due_at')
            ->whereIn('room_id', $roomIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get();
    }

    public function setManualStatus(Room $room, array $dates, RoomAvailabilityStatus $status): int
    {
        return DB::transaction(function () use ($room, $dates, $status): int {
            $existing = $room->availability()->whereIn('date', $dates)->lockForUpdate()->get()
                ->keyBy(fn (RoomAvailability $day): string => $day->date->toDateString());
            $changed = 0;

            foreach ($dates as $date) {
                $day = $existing->get($date);

                if ($day !== null && $day->status !== RoomAvailabilityStatus::Open && $day->status !== RoomAvailabilityStatus::Closed && $day->blocksBooking()) {
                    continue;
                }

                if ($status === RoomAvailabilityStatus::Open) {
                    if ($day !== null) {
                        $day->price_override !== null ? $day->update(['status' => $status, 'booking_id' => null, 'held_until' => null]) : $day->delete();
                        $changed++;
                    }

                    continue;
                }

                $room->availability()->updateOrCreate(['date' => $date], ['status' => $status, 'booking_id' => null, 'held_until' => null]);
                $changed++;
            }

            return $changed;
        });
    }

    public function forCatalogue(?string $destination, array $tokens): Collection
    {
        $query = $this->query()
            ->bookable()
            ->with(['building.destination', 'seasonPrices', 'productPrices'])
            ->when($destination, fn (Builder $query, string $slug) => $query->whereHas('building.destination', fn (Builder $destination) => $destination->where('slug', $slug)));

        if ($tokens !== []) {
            $this->matchTokens($query, $tokens, ['name', 'building.name']);
        }

        return $query->orderBy('building_id')->orderBy('sort')->get();
    }
}
