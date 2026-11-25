<?php

namespace App\DTOs\Booking;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Drag and drop on the occupancy calendar: one room of a booking moved to another room and/or check-in date.
 * The stay keeps its number of nights; the other rooms of the booking move with the dates.
 */
final readonly class CalendarMoveData
{
    public function __construct(
        public int $bookingId,
        public int $fromRoomId,
        public int $toRoomId,
        public CarbonImmutable $checkIn,
        public bool $dryRun = false,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            bookingId: (int) $request->validated('booking_id'),
            fromRoomId: (int) $request->validated('from_room_id'),
            toRoomId: (int) $request->validated('to_room_id'),
            checkIn: CarbonImmutable::parse($request->validated('check_in'))->startOfDay(),
            dryRun: (bool) $request->validated('dry_run', false),
        );
    }
}
