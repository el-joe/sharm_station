<?php

namespace App\Services;

use App\DTOs\Booking\BookingChangeData;
use App\DTOs\Booking\CalendarMoveData;
use App\DTOs\Pricing\AddonSelectionData;
use App\DTOs\Pricing\QuoteLineData;
use App\DTOs\Pricing\QuoteRequestData;
use App\DTOs\Pricing\QuoteResultData;
use App\DTOs\Pricing\RoomAllocationData;
use App\DTOs\Pricing\RoomOptionSelectionData;
use App\DTOs\Pricing\RoomQuoteRequestData;
use App\DTOs\Pricing\UnitSelectionData;
use App\Enums\ActivityAction;
use App\Enums\AddonPricingBasis;
use App\Enums\BookingStatus;
use App\Enums\RoomAvailabilityStatus;
use App\Enums\ServiceType;
use App\Enums\TravelerType;
use App\Exceptions\DomainValidationException;
use App\Models\Booking;
use App\Models\BookingAddon;
use App\Models\BookingRoom;
use App\Models\BookingTraveler;
use App\Models\BookingUnit;
use App\Models\Package;
use App\Models\Room;
use App\Models\Trip;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Services\Pricing\PackagePricingEngine;
use App\Services\Pricing\RoomPricingEngine;
use App\Services\Pricing\TripPricingEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

/**
 * Admin changes of an existing booking (SRS 12.3): dates and rooms of a stay (also by drag and drop on the
 * calendar); travel date, party (adults, children ages), units, add-ons and optional rooms of a trip, Nile trip or
 * package. The price is recalculated by the same engines, with the booking's own nationality group, partner
 * (commission) and currency, which also enforce min/max persons, unit seats, child policy and the date's
 * availability; the booking's own nights never block the change. A party change adds placeholder travellers or
 * removes the last ones, keeping the named travellers (children are matched by age).
 * preview() returns the diff shown before saving; apply() saves it, moves the room holds and logs the change.
 */
class BookingModificationService
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookings,
        private readonly RoomRepositoryInterface $rooms,
        private readonly RoomPricingEngine $roomPricing,
        private readonly TripPricingEngine $tripPricing,
        private readonly PackagePricingEngine $packagePricing,
        private readonly BookingRoomWriter $roomWriter,
        private readonly BookingService $bookingService,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * @return array{valid: bool, errors: array<string, list<string>>, diff: list<array{field: string, label: string, before: string, after: string, changed: bool}>, quote: array<string, mixed>}
     */
    public function preview(Booking $booking, BookingChangeData $change): array
    {
        $this->ensureEditable($booking);
        $quote = $this->quote($booking, $change);

        return [
            'valid' => $quote->isValid(),
            'errors' => $quote->errors,
            'diff' => $this->diff($booking, $change, $quote),
            'quote' => $quote->toArray($quote->isPartnerQuote()),
        ];
    }

    /**
     * @throws DomainValidationException when the change breaks a rule (availability, allocation, capacity...)
     */
    public function apply(Booking $booking, BookingChangeData $change, User $actor): Booking
    {
        $this->ensureEditable($booking);

        $diff = DB::transaction(function () use ($booking, $change): array {
            /** @var Booking $locked */
            $locked = $this->bookings->query()->lockForUpdate()->findOrFail($booking->id);
            $locked->load(['rooms.guests', 'travelers', 'units', 'addons', 'bookable']);

            if ($locked->service_type === ServiceType::Accommodation) {
                $this->rooms->lockManyForBooking($this->targetRooms($locked, $change));
            }

            $quote = $this->quote($locked, $change);

            if (! $quote->isValid()) {
                throw DomainValidationException::withErrors($quote->errors);
            }

            $diff = $this->diff($locked, $change, $quote);
            $locked->service_type === ServiceType::Accommodation
                ? $this->saveStay($locked, $change, $quote)
                : $this->saveTrip($locked, $change, $quote);

            return $diff;
        });

        $booking->refresh();
        $changed = array_values(array_filter($diff, fn (array $row): bool => $row['changed']));
        $summary = implode('; ', array_map(fn (array $row): string => "{$row['label']}: {$row['before']} → {$row['after']}", $changed));

        $booking->statusLogs()->create([
            'from_status' => $booking->status,
            'to_status' => $booking->status,
            'user_id' => $actor->id,
            'note' => trim('Booking modified. '.$summary.($change->note ? ' · '.$change->note : '')),
        ]);
        $this->activity->log(ActivityAction::BookingModified, $booking, "Booking {$booking->reference} modified: ".($summary !== '' ? $summary : 'price recalculated'), [
            'diff' => $changed,
            'note' => $change->note,
        ], $actor);

        return $booking;
    }

    /**
     * Calendar drag and drop: the dragged room goes to the target room and/or the new check-in, the other rooms of
     * the booking keep their room and follow the dates.
     *
     * @return array{booking: Booking, preview: array<string, mixed>}
     *
     * @throws DomainValidationException
     */
    public function move(CalendarMoveData $move, User $actor): array
    {
        /** @var Booking|null $booking */
        $booking = $this->bookings->query()->with('rooms')->find($move->bookingId);

        if ($booking === null || $booking->service_type !== ServiceType::Accommodation || $booking->check_in === null) {
            throw DomainValidationException::withErrors(['booking_id' => ['Only accommodation bookings can be moved on the calendar.']]);
        }

        if ($booking->rooms->firstWhere('room_id', $move->fromRoomId) === null) {
            throw DomainValidationException::withErrors(['from_room_id' => ['This room is not part of the booking.']]);
        }

        $nights = (int) $booking->check_in->diffInDays($booking->check_out);
        $change = new BookingChangeData(
            checkIn: $move->checkIn,
            checkOut: $move->checkIn->addDays($nights),
            rooms: array_map(
                fn (RoomAllocationData $room): RoomAllocationData => $room->roomId === $move->fromRoomId
                    ? new RoomAllocationData($move->toRoomId, $room->adults, $room->childAges, $room->extraBeds, $room->cots)
                    : $room,
                $this->currentAllocations($booking->load('travelers')),
            ),
            note: 'Moved on the calendar',
        );

        $preview = $this->preview($booking, $change);

        if ($move->dryRun || ! $preview['valid']) {
            return ['booking' => $booking, 'preview' => $preview];
        }

        return ['booking' => $this->apply($booking, $change, $actor), 'preview' => $preview];
    }

    /**
     * @throws DomainValidationException
     */
    private function ensureEditable(Booking $booking): void
    {
        if (in_array($booking->status, [...BookingStatus::voided(), BookingStatus::Completed], true)) {
            throw DomainValidationException::withErrors(['booking' => ['A '.strtolower($booking->status->label()).' booking cannot be changed.']]);
        }

        if ($booking->service_type === ServiceType::Transfer) {
            throw DomainValidationException::withErrors(['booking' => ['Transfer requests are changed from the transfer request page.']]);
        }
    }

    private function quote(Booking $booking, BookingChangeData $change): QuoteResultData
    {
        $booking->loadMissing(['rooms', 'travelers', 'units', 'addons', 'bookable', 'partner']);

        if ($booking->service_type === ServiceType::Accommodation && $change->changesComposition()) {
            throw DomainValidationException::withErrors(['adults' => ['The guests of a stay are changed room by room.']]);
        }
        $partner = $booking->partner?->isActive() ? $booking->partner : null;

        $quote = $booking->service_type === ServiceType::Accommodation
            ? $this->stayQuote($booking, $change, $partner)
            : $this->tripQuote($booking, $change, $partner);

        return (float) $booking->discount_amount > 0
            ? $quote->withDiscount((float) $booking->discount_amount, 'Coupon '.$booking->coupon_code, ['coupon_code' => $booking->coupon_code])
            : $quote;
    }

    private function stayQuote(Booking $booking, BookingChangeData $change, mixed $partner): QuoteResultData
    {
        $allocations = $change->rooms ?? $this->currentAllocations($booking);
        $party = $this->party($booking);
        $request = new RoomQuoteRequestData(
            checkIn: $change->checkIn ?? $booking->check_in,
            checkOut: $change->checkOut ?? $booking->check_out,
            adults: $party['adults'],
            children: count($party['child_ages']),
            childAges: $party['child_ages'],
            partner: $partner,
            currency: $booking->currency,
            nationalityCode: $booking->nationality_code,
            nationalityGroupId: $booking->nationality_group_id,
            rooms: $allocations,
            multiRoom: true,
            partyGiven: true,
            ignoreBookingId: $booking->id,
        );

        $rooms = $this->rooms->findBookableMany($request->roomIds());

        if ($rooms->isEmpty()) {
            throw DomainValidationException::withErrors(['rooms' => ['Choose at least one available room.']]);
        }

        return $this->roomPricing->quoteRooms($rooms, $request);
    }

    private function tripQuote(Booking $booking, BookingChangeData $change, mixed $partner): QuoteResultData
    {
        $party = $this->targetParty($booking, $change);
        $request = new QuoteRequestData(
            bookingType: $booking->booking_type,
            travelDate: ($change->travelDate ?? $booking->start_date)->startOfDay()->toImmutable(),
            adults: $party['adults'],
            children: count($party['child_ages']),
            childAges: $party['child_ages'],
            units: $change->units ?? $booking->units->map(fn (BookingUnit $unit): UnitSelectionData => new UnitSelectionData((int) $unit->trip_unit_option_id, (int) $unit->quantity))->values()->all(),
            addons: $change->addons ?? $this->currentAddons($booking),
            partner: $partner,
            currency: $booking->currency,
            nationalityCode: $booking->nationality_code,
            nationalityGroupId: $booking->nationality_group_id,
            roomOptions: $change->roomOptions ?? $this->currentRoomOptions($booking),
        );

        $product = $booking->bookable;

        return match (true) {
            $product instanceof Package => $this->packagePricing->quote($product, $request),
            $product instanceof Trip => $this->tripPricing->quote($product, $request),
            default => throw DomainValidationException::withErrors(['booking' => ['The product of this booking no longer exists.']]),
        };
    }

    private function saveStay(Booking $booking, BookingChangeData $change, QuoteResultData $quote): void
    {
        $status = $this->roomWriter->statusFor($booking);
        $roomIds = array_values(array_unique(array_map(fn (array $room): int => $room['room_id'], $quote->context['rooms'])));
        $rooms = $this->rooms->findBookableMany($roomIds)->keyBy('id');
        $checkIn = $change->checkIn ?? $booking->check_in;
        $checkOut = $change->checkOut ?? $booking->check_out;
        /** @var Room $first */
        $first = $rooms->get($quote->context['rooms'][0]['room_id']);

        $this->roomWriter->releaseNights($booking);
        $booking->rooms()->delete();
        $this->roomWriter->storeStayRooms($booking, $rooms, $quote, $booking->travelers->all());
        $this->roomWriter->blockNights($booking, $quote, $status, $status === RoomAvailabilityStatus::Held ? ($booking->payment_due_at ?? now()->addMinutes(BookingService::DEFAULT_HOLD_MINUTES)) : null);

        $this->saveTotals($booking, $quote, [
            'bookable_type' => $first->getMorphClass(),
            'bookable_id' => $first->id,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'start_date' => $checkIn->toDateString(),
            'end_date' => $checkOut->toDateString(),
        ]);
    }

    private function saveTrip(Booking $booking, BookingChangeData $change, QuoteResultData $quote): void
    {
        $start = $change->travelDate ?? $booking->start_date;
        $days = $booking->bookable instanceof Trip || $booking->bookable instanceof Package ? max(1, (int) $booking->bookable->days) : 1;
        $party = $this->targetParty($booking, $change);

        $booking->units()->delete();
        $booking->addons()->delete();
        $booking->rooms()->delete();
        $travelers = $this->syncTravelers($booking, $party['adults'], $party['child_ages']);
        $this->bookingService->storeUnits($booking, $quote);
        $this->bookingService->storeAddons($booking, $quote);
        $this->roomWriter->storeRoomOptions($booking, $quote, $travelers);

        if ($booking->adults + $booking->children !== $party['adults'] + count($party['child_ages'])) {
            $booking->includedTransfers()->update(['passengers' => $party['adults'] + count($party['child_ages'])]);
        }

        if ($start !== null && $booking->start_date !== null && ! $start->equalTo($booking->start_date)) {
            $shift = (int) $booking->start_date->diffInDays($start, false);

            foreach ($booking->includedTransfers()->get() as $transfer) {
                $transfer->update(['date' => $transfer->date?->addDays($shift)]);
            }
        }

        $this->saveTotals($booking, $quote, [
            'start_date' => $start?->toDateString(),
            'end_date' => $start?->addDays($days - 1)->toDateString(),
            'adults' => $party['adults'],
            'children' => count($party['child_ages']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function saveTotals(Booking $booking, QuoteResultData $quote, array $attributes): void
    {
        $this->bookings->update($booking, [
            ...$attributes,
            'season_name' => $quote->seasonName,
            'retail_total' => $quote->retailTotal,
            'partner_total' => $quote->partnerTotal,
            'commission_amount' => $quote->commissionAmount,
            'commission_percent' => $quote->commissionPercent,
            'price_rule' => $quote->priceRule,
            'total' => $quote->total,
            'deposit_amount' => $quote->depositAmount,
            'balance_amount' => $quote->balanceAmount,
            'total_in_currency' => round($quote->total * (float) $booking->exchange_rate, 2),
            'price_lines' => array_map(fn (QuoteLineData $line): array => $line->toArray($quote->isPartnerQuote()), $quote->lines),
        ]);
    }

    /**
     * @return list<array{field: string, label: string, before: string, after: string, changed: bool}>
     */
    private function diff(Booking $booking, BookingChangeData $change, QuoteResultData $quote): array
    {
        $money = fn (float|string|null $amount): string => $amount === null ? '—' : Number::currency((float) $amount, 'EUR', 'en');
        $rows = [];

        if ($booking->service_type === ServiceType::Accommodation) {
            $rows[] = ['check_in', 'Check-in', $booking->check_in?->toDateString(), ($change->checkIn ?? $booking->check_in)?->toDateString()];
            $rows[] = ['check_out', 'Check-out', $booking->check_out?->toDateString(), ($change->checkOut ?? $booking->check_out)?->toDateString()];
            $rows[] = ['rooms', 'Rooms', $this->describeRooms($booking->rooms->map(fn (BookingRoom $room): array => [
                'name' => $room->room_name.($room->room_number ? ' #'.$room->room_number : ''), 'adults' => $room->adults, 'children' => count($room->child_ages ?? []), 'extra_beds' => $room->extra_beds, 'cots' => $room->cots,
            ])->all()), $this->describeRooms(array_map(fn (array $room): array => [
                'name' => $room['name'].($room['number'] ? ' #'.$room['number'] : ''), 'adults' => $room['adults'], 'children' => count($room['children_ages']), 'extra_beds' => $room['extra_beds'], 'cots' => $room['cots'],
            ], $quote->context['rooms'] ?? []))];
        } else {
            $party = $this->targetParty($booking, $change);
            $rows[] = ['start_date', 'Travel date', $booking->start_date?->toDateString(), ($change->travelDate ?? $booking->start_date)?->toDateString()];
            $rows[] = ['party', 'Travellers', $this->describeParty($this->party($booking)), $this->describeParty($party)];

            if ($booking->units->isNotEmpty() || $quote->linesOfType(QuoteLineData::TYPE_UNIT) !== []) {
                $rows[] = ['units', 'Units', $this->describeItems($booking->units->map(fn (BookingUnit $unit): array => [$unit->name, (int) $unit->quantity])->all()),
                    $this->describeItems(array_map(fn (QuoteLineData $line): array => [$line->label, $line->quantity], $quote->linesOfType(QuoteLineData::TYPE_UNIT)))];
            }

            $rows[] = ['addons', 'Add-ons', $this->describeItems($booking->addons->map(fn (BookingAddon $addon): array => [$addon->name, (int) $addon->quantity])->all()),
                $this->describeItems(array_map(fn (QuoteLineData $line): array => [$line->label, $line->quantity], $quote->linesOfType(QuoteLineData::TYPE_ADDON)))];
            $rows[] = ['rooms', 'Rooms', $this->describeRooms($booking->rooms->map(fn (BookingRoom $room): array => [
                'name' => $room->room_name, 'adults' => $room->adults, 'children' => count($room->child_ages ?? []), 'extra_beds' => 0, 'cots' => 0,
            ])->all()), $this->describeRooms(array_map(fn (QuoteLineData $line): array => [
                'name' => $line->label, 'adults' => $line->meta['adults'], 'children' => count($line->meta['children_ages']), 'extra_beds' => 0, 'cots' => 0,
            ], $quote->linesOfType(QuoteLineData::TYPE_ROOM)))];
        }

        $rows[] = ['total', 'Total', $money($booking->total), $money($quote->total)];
        $rows[] = ['deposit_amount', 'Deposit', $money($booking->deposit_amount), $money($quote->depositAmount)];
        $rows[] = ['balance_amount', 'Balance', $money($booking->balance_amount), $money($quote->balanceAmount)];

        if ($booking->partner_id !== null) {
            $rows[] = ['commission_amount', 'Partner commission', $money($booking->commission_amount), $money($quote->commissionAmount)];
        }

        return array_map(fn (array $row): array => [
            'field' => $row[0],
            'label' => $row[1],
            'before' => (string) ($row[2] ?? '—'),
            'after' => (string) ($row[3] ?? '—'),
            'changed' => (string) ($row[2] ?? '') !== (string) ($row[3] ?? ''),
        ], $rows);
    }

    /**
     * @param  list<array{name: string, adults: int, children: int, extra_beds: int, cots: int}>  $rooms
     */
    private function describeRooms(array $rooms): string
    {
        if ($rooms === []) {
            return 'None';
        }

        return implode('; ', array_map(fn (array $room): string => $room['name'].' ('.$room['adults'].' ad.'
            .($room['children'] > 0 ? ' + '.$room['children'].' ch.' : '')
            .($room['extra_beds'] > 0 ? ', '.$room['extra_beds'].' extra bed' : '')
            .($room['cots'] > 0 ? ', '.$room['cots'].' cot' : '').')', $rooms));
    }

    /**
     * @param  array{adults: int, child_ages: list<int>}  $party
     */
    private function describeParty(array $party): string
    {
        $ages = $party['child_ages'];
        sort($ages);

        return $party['adults'].' '.($party['adults'] === 1 ? 'adult' : 'adults')
            .($ages !== [] ? ', '.count($ages).' '.(count($ages) === 1 ? 'child' : 'children').' (age '.implode(', ', $ages).')' : '');
    }

    /**
     * @param  list<array{0: string, 1: int}>  $items  name and quantity
     */
    private function describeItems(array $items): string
    {
        return $items === [] ? 'None' : implode('; ', array_map(fn (array $item): string => $item[1].' × '.$item[0], $items));
    }

    /**
     * @return list<AddonSelectionData>
     */
    private function currentAddons(Booking $booking): array
    {
        return $booking->addons->map(fn (BookingAddon $addon): AddonSelectionData => new AddonSelectionData(
            (int) $addon->addon_id,
            $addon->pricing_basis === AddonPricingBasis::PerUnit ? (int) $addon->quantity : null,
        ))->values()->all();
    }

    /**
     * Party after the change: the new adults / children ages, else the booking's own.
     *
     * @return array{adults: int, child_ages: list<int>}
     */
    private function targetParty(Booking $booking, BookingChangeData $change): array
    {
        $current = $this->party($booking);

        return [
            'adults' => $change->adults ?? $current['adults'],
            'child_ages' => $change->childAges ?? $current['child_ages'],
        ];
    }

    /**
     * Brings the travellers in line with the new party: named travellers are kept (children matched by age, then
     * re-aged), missing ones are added as placeholders ("Adult 3", "Child 2"), surplus ones removed from the end.
     *
     * @param  list<int>  $childAges
     * @return list<BookingTraveler>
     */
    private function syncTravelers(Booking $booking, int $adults, array $childAges): array
    {
        $travelers = $booking->travelers()->orderBy('sort')->orderBy('id')->get();
        $adultRows = $travelers->filter(fn (BookingTraveler $traveler): bool => $traveler->type === TravelerType::Adult)->values();
        $childRows = $travelers->filter(fn (BookingTraveler $traveler): bool => $traveler->type === TravelerType::Child)->values();
        $lastName = $adultRows->first()?->last_name ?? (str_contains((string) $booking->lead_name, ' ') ? explode(' ', trim((string) $booking->lead_name), 2)[1] : '-');
        $sort = (int) $travelers->max('sort') + 1;

        $adultRows->slice($adults)->each(fn (BookingTraveler $traveler) => $traveler->delete());

        for ($number = $adultRows->count() + 1; $number <= $adults; $number++) {
            $booking->travelers()->create(['first_name' => 'Adult '.$number, 'last_name' => $lastName, 'type' => TravelerType::Adult, 'age' => null, 'sort' => $sort++]);
        }

        $unmatchedAges = $childAges;
        $unmatchedRows = [];

        foreach ($childRows as $traveler) {
            $position = array_search($traveler->age !== null ? (int) $traveler->age : null, $unmatchedAges, true);

            if ($position === false) {
                $unmatchedRows[] = $traveler;
            } else {
                unset($unmatchedAges[$position]);
            }
        }

        $number = count($childAges) - count($unmatchedAges);

        foreach (array_values($unmatchedAges) as $age) {
            $traveler = array_shift($unmatchedRows);
            $number++;
            $traveler !== null
                ? $traveler->update(['age' => $age])
                : $booking->travelers()->create(['first_name' => 'Child '.$number, 'last_name' => $lastName, 'type' => TravelerType::Child, 'age' => $age, 'sort' => $sort++]);
        }

        foreach ($unmatchedRows as $traveler) {
            $traveler->delete();
        }

        $booking->unsetRelation('travelers');

        return $booking->travelers()->orderBy('sort')->orderBy('id')->get()->all();
    }

    /**
     * @return list<RoomAllocationData>
     */
    private function currentAllocations(Booking $booking): array
    {
        $rooms = $booking->rooms->filter(fn (BookingRoom $room): bool => $room->room_id !== null)->values();

        // Single-room bookings made before multi-room booking stored no occupancy on the room: the whole party sleeps in it.
        if ($rooms->count() === 1 && $rooms[0]->occupants() === 0) {
            $party = $this->party($booking);

            return [new RoomAllocationData((int) $rooms[0]->room_id, $party['adults'], $party['child_ages'], $rooms[0]->extra_beds, $rooms[0]->cots)];
        }

        return $rooms->map(fn (BookingRoom $room): RoomAllocationData => new RoomAllocationData((int) $room->room_id, $room->adults, $room->child_ages ?? [], $room->extra_beds, $room->cots))->all();
    }

    /**
     * @return list<RoomOptionSelectionData>
     */
    private function currentRoomOptions(Booking $booking): array
    {
        return $booking->rooms->filter(fn (BookingRoom $room): bool => $room->room_option_id !== null)
            ->map(fn (BookingRoom $room): RoomOptionSelectionData => new RoomOptionSelectionData((int) $room->room_option_id, $room->adults, $room->child_ages ?? []))
            ->values()->all();
    }

    /**
     * @return list<int>
     */
    private function targetRooms(Booking $booking, BookingChangeData $change): array
    {
        return array_values(array_unique(array_map(fn (RoomAllocationData $room): int => $room->roomId, $change->rooms ?? $this->currentAllocations($booking))));
    }

    /**
     * Party of the booking: adults and children ages from the travellers (else from the stored rooms or counts).
     *
     * @return array{adults: int, child_ages: list<int>}
     */
    private function party(Booking $booking): array
    {
        $children = $booking->travelers->filter(fn (BookingTraveler $traveler): bool => $traveler->type === TravelerType::Child);
        $ages = $children->pluck('age')->filter(fn ($age): bool => $age !== null)->map(fn ($age): int => (int) $age)->values()->all();

        if (count($ages) !== $booking->children) {
            $fromRooms = $booking->rooms->flatMap(fn (BookingRoom $room): array => $room->child_ages ?? [])->map(fn ($age): int => (int) $age)->values()->all();
            $ages = count($fromRooms) === $booking->children ? $fromRooms : array_pad(array_slice($ages, 0, $booking->children), $booking->children, 8);
        }

        return ['adults' => $booking->adults, 'child_ages' => $ages];
    }
}
