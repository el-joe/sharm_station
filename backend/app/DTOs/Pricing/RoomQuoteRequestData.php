<?php

namespace App\DTOs\Pricing;

use App\Models\Partner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Accommodation stay request: one or more rooms of the same building with their guests (SRS 6.4, 7.2).
 * The legacy single-room payload (room_id + adults + children + child_ages) becomes one allocation.
 */
final readonly class RoomQuoteRequestData
{
    /**
     * @param  list<int>  $childAges  the whole party's children ages
     * @param  list<RoomAllocationData>  $rooms
     */
    public function __construct(
        public CarbonImmutable $checkIn,
        public CarbonImmutable $checkOut,
        public int $adults,
        public int $children = 0,
        public array $childAges = [],
        public ?Partner $partner = null,
        public string $currency = 'EUR',
        public ?string $nationalityCode = null,
        public ?int $nationalityGroupId = null,
        public array $rooms = [],
        public bool $multiRoom = false,
        public bool $partyGiven = false,
        public ?int $ignoreBookingId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?Partner $partner = null, string $currency = 'EUR', ?string $nationalityCode = null, ?int $nationalityGroupId = null, ?int $ignoreBookingId = null): self
    {
        $checkIn = CarbonImmutable::parse($data['check_in'])->startOfDay();
        $checkOut = CarbonImmutable::parse($data['check_out'])->startOfDay();

        if (! empty($data['rooms']) && is_array($data['rooms'])) {
            $rooms = array_values(array_map(fn (array $room): RoomAllocationData => RoomAllocationData::fromArray($room), $data['rooms']));
            $partyGiven = isset($data['adults']);
            $allAges = array_merge([], ...array_map(fn (RoomAllocationData $room): array => $room->childAges, $rooms));
            $childAges = $partyGiven ? array_values(array_map('intval', $data['child_ages'] ?? [])) : $allAges;

            return new self(
                checkIn: $checkIn,
                checkOut: $checkOut,
                adults: $partyGiven ? (int) $data['adults'] : array_sum(array_map(fn (RoomAllocationData $room): int => $room->adults, $rooms)),
                children: $partyGiven ? (int) ($data['children'] ?? count($childAges)) : count($allAges),
                childAges: $childAges,
                partner: $partner,
                currency: $currency,
                nationalityCode: $nationalityCode,
                nationalityGroupId: $nationalityGroupId,
                rooms: $rooms,
                multiRoom: true,
                partyGiven: $partyGiven,
                ignoreBookingId: $ignoreBookingId,
            );
        }

        $adults = (int) ($data['adults'] ?? 1);
        $children = (int) ($data['children'] ?? 0);
        $childAges = array_values(array_map('intval', $data['child_ages'] ?? []));

        return new self(
            checkIn: $checkIn,
            checkOut: $checkOut,
            adults: $adults,
            children: $children,
            childAges: $childAges,
            partner: $partner,
            currency: $currency,
            nationalityCode: $nationalityCode,
            nationalityGroupId: $nationalityGroupId,
            rooms: [new RoomAllocationData(
                roomId: (int) ($data['room_id'] ?? $data['id'] ?? 0),
                adults: $adults,
                childAges: array_slice($childAges, 0, $children),
                extraBeds: (int) ($data['extra_beds'] ?? 0),
                cots: (int) ($data['cots'] ?? 0),
            )],
            ignoreBookingId: $ignoreBookingId,
        );
    }

    public static function fromRequest(FormRequest $request, ?Partner $partner = null, string $currency = 'EUR', ?string $nationalityCode = null, ?int $nationalityGroupId = null): self
    {
        return self::fromArray($request->validated(), $partner, $currency, $nationalityCode, $nationalityGroupId);
    }

    public function nights(): int
    {
        return (int) $this->checkIn->diffInDays($this->checkOut, false);
    }

    /**
     * @return list<int>
     */
    public function roomIds(): array
    {
        return array_values(array_unique(array_map(fn (RoomAllocationData $room): int => $room->roomId, $this->rooms)));
    }

    /**
     * Same request priced for one room (single-room quote of a known room).
     */
    public function forRoom(int $roomId): self
    {
        if ($this->multiRoom || $this->rooms === []) {
            return $this->rooms === [] ? $this->withRooms([new RoomAllocationData($roomId, $this->adults, array_slice($this->childAges, 0, $this->children))], false) : $this;
        }

        $room = $this->rooms[0];

        return $this->withRooms([new RoomAllocationData($roomId, $room->adults, $room->childAges, $room->extraBeds, $room->cots)], false);
    }

    /**
     * @param  list<RoomAllocationData>  $rooms
     */
    public function withRooms(array $rooms, bool $multiRoom = true, ?CarbonImmutable $checkIn = null, ?CarbonImmutable $checkOut = null): self
    {
        $ages = array_merge([], ...array_map(fn (RoomAllocationData $room): array => $room->childAges, $rooms));

        return new self(
            checkIn: $checkIn ?? $this->checkIn,
            checkOut: $checkOut ?? $this->checkOut,
            adults: $multiRoom ? array_sum(array_map(fn (RoomAllocationData $room): int => $room->adults, $rooms)) : $this->adults,
            children: $multiRoom ? count($ages) : $this->children,
            childAges: $multiRoom ? $ages : $this->childAges,
            partner: $this->partner,
            currency: $this->currency,
            nationalityCode: $this->nationalityCode,
            nationalityGroupId: $this->nationalityGroupId,
            rooms: $rooms,
            multiRoom: $multiRoom,
            partyGiven: false,
            ignoreBookingId: $this->ignoreBookingId,
        );
    }

    public function ignoringBooking(?int $bookingId): self
    {
        return new self($this->checkIn, $this->checkOut, $this->adults, $this->children, $this->childAges, $this->partner, $this->currency,
            $this->nationalityCode, $this->nationalityGroupId, $this->rooms, $this->multiRoom, $this->partyGiven, $bookingId);
    }
}
