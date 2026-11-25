<?php

namespace App\Services;

use App\DTOs\Pricing\QuoteLineData;
use App\DTOs\Pricing\QuoteResultData;
use App\Enums\RoomAvailabilityStatus;
use App\Enums\TravelerType;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingTraveler;
use App\Models\Room;
use App\Models\RoomAvailability;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Writes the rooms of a booking from its quote (SRS 6.4, 7.1, 7.2): accommodation rooms with their guests, extra
 * beds and cots, optional trip/package rooms per stage, and the nights held or booked in the room calendars.
 * Runs inside the caller's booking transaction.
 */
class BookingRoomWriter
{
    /**
     * Accommodation rooms of the quote (context "rooms"), each with its allocated travellers.
     *
     * @param  Collection<int, Room>  $rooms  keyed by id
     * @param  list<BookingTraveler>  $travelers
     */
    public function storeStayRooms(Booking $booking, Collection $rooms, QuoteResultData $quote, array $travelers): void
    {
        $summaries = $quote->context['rooms'] ?? [];
        $allocations = $this->allocateTravelers($travelers, array_map(fn (array $room): array => ['adults' => $room['adults'], 'child_ages' => $room['children_ages']], $summaries));

        foreach ($summaries as $position => $summary) {
            /** @var Room $room */
            $room = $rooms->get($summary['room_id']);
            $roomLines = array_filter($quote->lines, fn (QuoteLineData $line): bool => ($line->meta['room_index'] ?? null) === $summary['index']);
            $partnerTotal = $quote->isPartnerQuote() ? round(array_sum(array_map(fn (QuoteLineData $line): float => (float) $line->partnerTotal, $roomLines)), 2) : null;

            $bookingRoom = $booking->rooms()->create([
                'room_id' => $room->id,
                'room_name' => (string) $room->name,
                'room_number' => $room->number,
                'pricing_method' => $room->pricing_mode->value,
                'adults' => $summary['adults'],
                'child_ages' => $summary['children_ages'],
                'extra_beds' => $summary['extra_beds'],
                'cots' => $summary['cots'],
                'nights' => $summary['nights'],
                'price' => $summary['nights'] > 0 ? round($summary['room_total'] / $summary['nights'], 2) : 0,
                'total' => $summary['total'],
                'extras_total' => $summary['extras_total'],
                'partner_total' => $partnerTotal,
            ]);

            $this->attachGuests($bookingRoom, $allocations[$position] ?? [], $summary['guests'] ?? []);
        }
    }

    /**
     * Optional rooms of a trip or package (quote lines of type "room"); travellers are allocated per stage.
     *
     * @param  list<BookingTraveler>  $travelers
     */
    public function storeRoomOptions(Booking $booking, QuoteResultData $quote, array $travelers): void
    {
        $byStage = [];

        foreach ($quote->linesOfType(QuoteLineData::TYPE_ROOM) as $line) {
            $byStage[(int) ($line->meta['stage_position'] ?? 0)][] = $line;
        }

        foreach ($byStage as $lines) {
            $allocations = $this->allocateTravelers($travelers, array_map(fn (QuoteLineData $line): array => ['adults' => $line->meta['adults'], 'child_ages' => $line->meta['children_ages']], $lines));

            foreach ($lines as $position => $line) {
                $bookingRoom = $booking->rooms()->create([
                    'room_option_id' => $line->meta['room_option_id'],
                    'stage_position' => $line->meta['stage_position'],
                    'room_name' => $line->label,
                    'pricing_method' => $line->meta['pricing_mode'],
                    'adults' => $line->meta['adults'],
                    'child_ages' => $line->meta['children_ages'],
                    'nights' => $line->meta['nights'],
                    'price' => $line->unitPrice,
                    'total' => $line->total,
                    'partner_total' => $line->partnerTotal,
                ]);

                $this->attachGuests($bookingRoom, $allocations[$position] ?? [], []);
            }
        }
    }

    /**
     * Blocks the quoted nights of every room for the booking: held until the payment deadline, or booked.
     */
    public function blockNights(Booking $booking, QuoteResultData $quote, RoomAvailabilityStatus $status, ?CarbonInterface $holdUntil = null): void
    {
        foreach ($quote->linesOfType(QuoteLineData::TYPE_NIGHT) as $line) {
            RoomAvailability::query()->updateOrCreate(
                ['room_id' => $line->meta['room_id'], 'date' => $line->meta['date']],
                [
                    'status' => $status,
                    'booking_id' => $booking->id,
                    'room_ical_feed_id' => null,
                    'held_until' => $status === RoomAvailabilityStatus::Held ? $holdUntil : null,
                ],
            );
        }
    }

    /**
     * Frees every night the booking holds or books (price overrides on those dates are kept).
     */
    public function releaseNights(Booking $booking): void
    {
        RoomAvailability::query()->where('booking_id', $booking->id)->whereNotNull('price_override')
            ->update(['status' => RoomAvailabilityStatus::Open, 'booking_id' => null, 'held_until' => null]);
        RoomAvailability::query()->where('booking_id', $booking->id)->delete();
    }

    /**
     * Calendar status of a booking's nights: booked once paid or confirmed, otherwise held.
     */
    public function statusFor(Booking $booking): RoomAvailabilityStatus
    {
        $held = RoomAvailability::query()->where('booking_id', $booking->id)->where('status', RoomAvailabilityStatus::Held)->exists();

        return $held || in_array($booking->status->value, ['awaiting_payment', 'awaiting_receipt_review'], true)
            ? RoomAvailabilityStatus::Held
            : RoomAvailabilityStatus::Booked;
    }

    /**
     * Splits travellers into rooms: adults in order, children matched by age when possible. Travellers left over
     * (types not matching the allocation) go to the last room so nobody is lost.
     *
     * @param  list<BookingTraveler>  $travelers
     * @param  list<array{adults: int, child_ages: list<int>}>  $rooms
     * @return list<list<BookingTraveler>>
     */
    public function allocateTravelers(array $travelers, array $rooms): array
    {
        $adults = array_values(array_filter($travelers, fn (BookingTraveler $traveler): bool => $traveler->type === TravelerType::Adult));
        $children = array_values(array_filter($travelers, fn (BookingTraveler $traveler): bool => $traveler->type !== TravelerType::Adult));
        $result = [];

        foreach ($rooms as $position => $room) {
            $result[$position] = array_splice($adults, 0, $room['adults']);

            foreach ($room['child_ages'] as $age) {
                $match = null;

                foreach ($children as $index => $child) {
                    if ($child->age === $age) {
                        $match = $index;

                        break;
                    }
                }

                $match ??= array_key_first($children);

                if ($match !== null) {
                    $result[$position][] = $children[$match];
                    unset($children[$match]);
                }
            }
        }

        if ($result !== []) {
            $last = array_key_last($result);
            $result[$last] = [...$result[$last], ...$adults, ...array_values($children)];
        }

        return $result;
    }

    /**
     * @param  list<BookingTraveler>  $travelers
     * @param  list<array{type: string, age: int|null, total: float, discount: float}>  $prices  per-person breakdown
     */
    private function attachGuests(BookingRoom $room, array $travelers, array $prices): void
    {
        $adultPrices = array_values(array_filter($prices, fn (array $guest): bool => $guest['type'] === 'adult'));
        $childPrices = array_values(array_filter($prices, fn (array $guest): bool => $guest['type'] === 'child'));

        foreach ($travelers as $traveler) {
            $price = $traveler->type === TravelerType::Adult ? array_shift($adultPrices) : array_shift($childPrices);

            $room->guests()->create([
                'booking_traveler_id' => $traveler->id,
                'guest_price' => $price['total'] ?? null,
                'discount_applied' => $price !== null && $price['discount'] > 0 ? $price['discount'] : null,
            ]);
        }
    }
}
