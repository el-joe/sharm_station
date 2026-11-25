<?php

namespace App\DTOs\Booking;

use App\DTOs\Pricing\AddonSelectionData;
use App\DTOs\Pricing\RoomAllocationData;
use App\DTOs\Pricing\RoomOptionSelectionData;
use App\DTOs\Pricing\UnitSelectionData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin change of a booking (SRS 12.3): accommodation check-in/out and rooms with their guests, or for a trip,
 * Nile trip or package the travel date, the party (adults and children ages), the units of per-unit trips, the
 * add-ons and the optional rooms. Every null field keeps the booking's current value.
 */
final readonly class BookingChangeData
{
    /**
     * @param  list<RoomAllocationData>|null  $rooms  accommodation rooms (null = keep)
     * @param  list<RoomOptionSelectionData>|null  $roomOptions  trip/package rooms (null = keep)
     * @param  list<int>|null  $childAges  trip/package children ages (null = keep)
     * @param  list<UnitSelectionData>|null  $units  per-unit trip units (null = keep)
     * @param  list<AddonSelectionData>|null  $addons  selected add-ons, required ones are added by the engine (null = keep)
     */
    public function __construct(
        public ?CarbonImmutable $checkIn = null,
        public ?CarbonImmutable $checkOut = null,
        public ?CarbonImmutable $travelDate = null,
        public ?array $rooms = null,
        public ?array $roomOptions = null,
        public ?string $note = null,
        public ?int $adults = null,
        public ?array $childAges = null,
        public ?array $units = null,
        public ?array $addons = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            checkIn: filled($data['check_in'] ?? null) ? CarbonImmutable::parse($data['check_in'])->startOfDay() : null,
            checkOut: filled($data['check_out'] ?? null) ? CarbonImmutable::parse($data['check_out'])->startOfDay() : null,
            travelDate: filled($data['travel_date'] ?? null) ? CarbonImmutable::parse($data['travel_date'])->startOfDay() : null,
            rooms: isset($data['rooms']) ? array_values(array_map(fn (array $room): RoomAllocationData => RoomAllocationData::fromArray($room), $data['rooms'])) : null,
            roomOptions: array_key_exists('room_options', $data) ? array_values(array_map(RoomOptionSelectionData::fromArray(...), $data['room_options'] ?? [])) : null,
            note: $data['note'] ?? null,
            adults: isset($data['adults']) ? (int) $data['adults'] : null,
            childAges: array_key_exists('child_ages', $data) ? array_values(array_map('intval', $data['child_ages'] ?? [])) : null,
            units: array_key_exists('units', $data) ? array_values(array_map(UnitSelectionData::fromArray(...), array_filter($data['units'] ?? [], fn (array $unit): bool => (int) ($unit['quantity'] ?? 0) > 0))) : null,
            addons: array_key_exists('addons', $data) ? array_values(array_map(AddonSelectionData::fromArray(...), $data['addons'] ?? [])) : null,
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * Whether the change touches the party, units or add-ons (trip, Nile trip and package bookings only).
     */
    public function changesComposition(): bool
    {
        return $this->adults !== null || $this->childAges !== null || $this->units !== null || $this->addons !== null;
    }
}
