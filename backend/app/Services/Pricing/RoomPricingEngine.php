<?php

namespace App\Services\Pricing;

use App\DTOs\Pricing\QuoteLineData;
use App\DTOs\Pricing\QuoteResultData;
use App\DTOs\Pricing\RoomAllocationData;
use App\DTOs\Pricing\RoomQuoteRequestData;
use App\Enums\RoomPricingMode;
use App\Enums\ServiceType;
use App\Models\ChildPolicy;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Services\CurrencyService;
use App\Services\NationalityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Accommodation stay pricing (SRS 6.4, 6.6, 7.2–7.4, 3.8, 3.9): one or more rooms of the same building, each night
 * priced on its own — a date override, else the nationality/season matrix (group season → all-groups season →
 * group base → room base) — plus extra beds and cots. Per-person rooms price every guest with the children policy.
 * Allocation rules of SRS 7.2 are enforced here for quotes and bookings alike. No partner commission on accommodation.
 */
class RoomPricingEngine
{
    use BuildsQuotes;

    public const MAX_ROOMS = 10;

    public function __construct(
        private readonly PriceResolver $prices,
        private readonly CommissionResolver $commissions,
        private readonly NationalityService $nationalities,
        private readonly CurrencyService $currencies,
    ) {}

    /**
     * Single-room quote (legacy payload, room page widget).
     */
    public function quote(Room $room, RoomQuoteRequestData $request): QuoteResultData
    {
        return $this->quoteRooms(new Collection([$room]), $request->forRoom($room->id));
    }

    /**
     * Quote of every room of the request; rooms missing from $rooms are reported as unavailable.
     *
     * @param  Collection<int, Room>  $rooms
     */
    public function quoteRooms(Collection $rooms, RoomQuoteRequestData $request): QuoteResultData
    {
        $rooms->loadMissing(['building.childPolicy.bands', 'childPolicy.bands', 'seasonPrices', 'productPrices']);
        $rooms = $rooms->keyBy('id');

        // Accommodation has no partner commission (CR-11): partners pay the customer price.
        $this->beginQuote($request->partner, null, $request->nationalityGroupId, $request->nationalityCode);
        $nights = $request->nights();

        $this->validateDates($request, $nights);
        $this->validateParty($rooms, $request);

        $lines = [];
        $seasonNames = [];
        $summaries = [];
        $taxNights = 0;

        foreach ($request->rooms as $index => $allocation) {
            /** @var Room|null $room */
            $room = $rooms->get($allocation->roomId);

            if ($room === null) {
                $this->addError($request->multiRoom ? "rooms.{$index}.room_id" : 'room_id', __('allocation.room_unavailable'));

                continue;
            }

            $this->validateRoom($room, $allocation, $index, $request, $nights);

            if ($nights < 1) {
                continue;
            }

            $priced = $this->priceRoom($room, $allocation, $index, $request);
            array_push($lines, ...$priced['lines']);
            array_push($seasonNames, ...$priced['seasons']);
            $summaries[] = $priced['summary'];
            $taxNights += $nights;
        }

        $building = $rooms->first()?->building;

        if ($building !== null && ! $building->taxes_included && $building->tax_per_night !== null && $taxNights > 0) {
            $lines[] = $this->line(QuoteLineData::TYPE_TAX, 'Taxes and city fees', $taxNights, (float) $building->tax_per_night, [], commissionable: false);
        }

        $seasonNames = array_values(array_unique($seasonNames));
        $first = $rooms->get($request->rooms[0]->roomId ?? 0) ?? $rooms->first();

        return $this->buildResult(
            serviceType: ServiceType::Accommodation,
            lines: $lines,
            seasonName: $seasonNames === [] ? null : implode(', ', $seasonNames),
            depositPercent: (float) ($building->deposit_percent ?? 0),
            currency: $request->currency,
            context: [
                'room_id' => $first?->id,
                'building_id' => $building?->id,
                'pricing_mode' => $first?->pricing_mode->value,
                'check_in' => $request->checkIn->toDateString(),
                'check_out' => $request->checkOut->toDateString(),
                'nights' => max($nights, 0),
                'adults' => $request->adults,
                'children' => $request->children,
                'child_ages' => $request->childAges,
                'rooms' => $summaries,
                'tax_included' => (bool) ($building->taxes_included ?? true),
                'tax_per_night' => $building === null || $building->taxes_included ? null : $this->nullableFloat($building->tax_per_night),
            ],
        );
    }

    /**
     * Customer price of each night of the stay for a room (override, else nationality/season matrix), with the
     * nights that block the stay (booked, closed or held by another booking).
     *
     * @return list<array{date: CarbonImmutable, price: float, season: string|null, source: string|null, blocked: bool}>
     */
    public function nightlyPrices(Room $room, CarbonImmutable $checkIn, CarbonImmutable $checkOut, ?int $nationalityGroupId, ?int $ignoreBookingId = null): array
    {
        $days = max(1, (int) $checkIn->diffInDays($checkOut, false));
        $availability = $room->relationLoaded('availability')
            ? $room->availability
            : $room->availability()->whereBetween('date', [$checkIn->toDateString(), $checkIn->addDays($days - 1)->toDateString()])->get();
        $availability = $availability->keyBy(fn (RoomAvailability $day): string => $day->date->toDateString());
        $nights = [];

        for ($night = $checkIn; $night->lt($checkOut); $night = $night->addDay()) {
            /** @var RoomAvailability|null $day */
            $day = $availability->get($night->toDateString());
            $override = $this->nullableFloat($day?->price_override);
            $prices = $override === null ? $this->prices->resolve($room, $nationalityGroupId, $night) : null;
            $season = $prices?->season();

            $nights[] = [
                'date' => $night,
                'price' => $override ?? $prices?->night() ?? (float) $room->base_price,
                'season' => $season !== null ? (string) $season->name : null,
                'source' => $override !== null ? 'date_override' : $prices?->source('price_per_night')?->value,
                'blocked' => $day !== null && $day->blocksBooking() && ($ignoreBookingId === null || $day->booking_id !== $ignoreBookingId),
            ];
        }

        return $nights;
    }

    /**
     * Price of one guest for one night in a per-person room, children by age band (never below zero).
     * Adults and children from the policy's "adult from" age pay the nightly rate.
     */
    public function guestNightPrice(float $nightPrice, ?int $childAge, ?ChildPolicy $policy, bool $discountAllowed): float
    {
        if ($childAge === null || $policy === null || $childAge >= $policy->adult_from_age || ! $discountAllowed) {
            return $nightPrice;
        }

        return $policy->bandForAge($childAge)?->applyTo($nightPrice) ?? $nightPrice;
    }

    /**
     * Room total for an occupancy over the given nights (per room: nightly price; per person: sum of guests),
     * extra beds and cots included.
     *
     * @param  list<array{price: float}>  $nights
     * @param  list<int>  $childAges
     */
    public function stayTotal(Room $room, array $nights, int $adults, array $childAges, int $extraBeds = 0, int $cots = 0): float
    {
        $policy = $room->childPolicy ?? $room->building?->childPolicy;
        $total = 0.0;

        foreach ($nights as $night) {
            $total += $room->pricing_mode === RoomPricingMode::PerPerson
                ? $this->perPersonNight($night['price'], $adults, $childAges, $policy)
                : $night['price'];
        }

        $total += count($nights) * ($extraBeds * (float) $room->extra_bed_price + $cots * (float) $room->cot_price);

        return round($total, 2);
    }

    /**
     * @return array{lines: list<QuoteLineData>, seasons: list<string>, summary: array<string, mixed>}
     */
    private function priceRoom(Room $room, RoomAllocationData $allocation, int $index, RoomQuoteRequestData $request): array
    {
        $policy = $room->childPolicy ?? $room->building->childPolicy;
        $label = $this->roomLabel($room, $index, $request);
        $nights = $this->nightlyPrices($room, $request->checkIn, $request->checkOut, $this->nationalityGroup?->id, $request->ignoreBookingId);
        $lines = [];
        $seasons = [];
        $guestTotals = [];
        $roomTotal = 0.0;
        $roomMeta = ['room_id' => $room->id, 'room_index' => $index];

        foreach ($nights as $night) {
            if ($night['blocked']) {
                $this->addError('check_in', __('allocation.not_available_on', [
                    'room' => $request->multiRoom ? $this->subject($index, $request) : __('allocation.this_room'),
                    'date' => $night['date']->format('j M Y'),
                ]));
            }

            if ($night['season'] !== null) {
                $seasons[] = $night['season'];
            }

            $unitPrice = $room->pricing_mode === RoomPricingMode::PerPerson
                ? $this->perPersonNight($night['price'], $allocation->adults, $allocation->childAges, $policy)
                : $night['price'];
            $roomTotal += round($unitPrice, 2);

            if ($room->pricing_mode === RoomPricingMode::PerPerson) {
                $this->addGuestNight($guestTotals, $night['price'], $allocation, $policy);
            }

            $lines[] = $this->line(
                QuoteLineData::TYPE_NIGHT,
                ($request->multiRoom ? $label.' · ' : '').$night['date']->format('D j M Y').($night['season'] !== null ? ' · '.$night['season'] : ''),
                1,
                $unitPrice,
                [
                    ...$roomMeta,
                    'date' => $night['date']->toDateString(),
                    'season' => $night['season'],
                    'price_per_night' => $night['price'],
                    'price_source' => $night['source'],
                ],
            );
        }

        $count = count($nights);
        $extras = 0.0;

        if ($allocation->extraBeds > 0) {
            $extraBedPrice = (float) $room->extra_bed_price;
            $lines[] = $this->line(QuoteLineData::TYPE_EXTRA_BED, ($request->multiRoom ? $label.' · ' : '').'Extra bed', $allocation->extraBeds * $count, $extraBedPrice, [...$roomMeta, 'beds' => $allocation->extraBeds, 'nights' => $count]);
            $extras += round($extraBedPrice * $allocation->extraBeds * $count, 2);
        }

        if ($allocation->cots > 0) {
            $cotPrice = (float) $room->cot_price;
            $lines[] = $this->line(QuoteLineData::TYPE_COT, ($request->multiRoom ? $label.' · ' : '').'Cot', $allocation->cots * $count, $cotPrice, [...$roomMeta, 'cots' => $allocation->cots, 'nights' => $count]);
            $extras += round($cotPrice * $allocation->cots * $count, 2);
        }

        return [
            'lines' => $lines,
            'seasons' => $seasons,
            'summary' => [
                'index' => $index,
                'room_id' => $room->id,
                'name' => (string) $room->name,
                'number' => $room->number,
                'room_type' => $room->room_type->value,
                'pricing_mode' => $room->pricing_mode->value,
                'adults' => $allocation->adults,
                'children_ages' => $allocation->childAges,
                'extra_beds' => $allocation->extraBeds,
                'cots' => $allocation->cots,
                'nights' => $count,
                'room_total' => round($roomTotal, 2),
                'extras_total' => round($extras, 2),
                'total' => round($roomTotal + $extras, 2),
                'guests' => $room->pricing_mode === RoomPricingMode::PerPerson ? array_values($guestTotals) : [],
            ],
        ];
    }

    /**
     * Per-person breakdown of a room (each guest's stay price and discount, SRS 7.4).
     *
     * @param  array<string, array{type: string, age: int|null, total: float, discount: float}>  $guestTotals
     */
    private function addGuestNight(array &$guestTotals, float $nightPrice, RoomAllocationData $allocation, ?ChildPolicy $policy): void
    {
        for ($adult = 0; $adult < $allocation->adults; $adult++) {
            $guestTotals["adult-{$adult}"] ??= ['type' => 'adult', 'age' => null, 'total' => 0.0, 'discount' => 0.0];
            $guestTotals["adult-{$adult}"]['total'] = round($guestTotals["adult-{$adult}"]['total'] + $nightPrice, 2);
        }

        $discounted = 0;

        foreach ($allocation->childAges as $position => $age) {
            $allowed = $policy?->max_discounted_children === null || $discounted < $policy->max_discounted_children;
            $price = $this->guestNightPrice($nightPrice, $age, $policy, $allowed);

            if ($price < $nightPrice) {
                $discounted++;
            }

            $guestTotals["child-{$position}"] ??= ['type' => 'child', 'age' => $age, 'total' => 0.0, 'discount' => 0.0];
            $guestTotals["child-{$position}"]['total'] = round($guestTotals["child-{$position}"]['total'] + $price, 2);
            $guestTotals["child-{$position}"]['discount'] = round($guestTotals["child-{$position}"]['discount'] + $nightPrice - $price, 2);
        }
    }

    private function validateDates(RoomQuoteRequestData $request, int $nights): void
    {
        if ($nights < 1) {
            $this->addError('check_out', __('allocation.check_out_after'));
        }

        if ($request->checkIn->lt(CarbonImmutable::today())) {
            $this->addError('check_in', __('allocation.check_in_past'));
        }

        if ($request->children > 0 && count($request->childAges) !== $request->children) {
            $this->addError('child_ages', __('allocation.child_ages'));
        }
    }

    /**
     * Whole-request allocation rules (SRS 7.2): exclusive rooms, same building, every guest in exactly one room.
     *
     * @param  Collection<int, Room>  $rooms
     */
    private function validateParty(Collection $rooms, RoomQuoteRequestData $request): void
    {
        if (! $request->multiRoom) {
            return;
        }

        if (count($request->rooms) > self::MAX_ROOMS) {
            $this->addError('rooms', __('allocation.too_many_rooms', ['max' => self::MAX_ROOMS]));
        }

        $seen = [];

        foreach ($request->rooms as $index => $allocation) {
            if (isset($seen[$allocation->roomId])) {
                $this->addError("rooms.{$index}.room_id", __('allocation.shared_room', ['room' => $this->subject($index, $request)]));
            }

            $seen[$allocation->roomId] = true;
        }

        $buildings = $rooms->only($request->roomIds())->pluck('building_id')->unique();

        if ($buildings->count() > 1) {
            $this->addError('rooms', __('allocation.same_building'));
        }

        if (! $request->partyGiven) {
            return;
        }

        $allocatedAdults = array_sum(array_map(fn (RoomAllocationData $room): int => $room->adults, $request->rooms));
        $allocatedAges = array_merge([], ...array_map(fn (RoomAllocationData $room): array => $room->childAges, $request->rooms));
        $party = $request->adults + $request->children;
        $capacity = array_sum(array_map(
            fn (RoomAllocationData $allocation): int => $rooms->get($allocation->roomId)?->capacityWith($allocation->extraBeds) ?? 0,
            $request->rooms,
        ));

        $sameAges = (function (array $a, array $b): bool {
            sort($a);
            sort($b);

            return $a === $b;
        })($allocatedAges, array_slice($request->childAges, 0, $request->children));

        if ($capacity < $party) {
            $this->addError('rooms', __('allocation.cannot_fit', ['count' => $party]));
        } elseif ($allocatedAdults !== $request->adults || ! $sameAges) {
            $this->addError('rooms', __('allocation.every_guest'));
        }
    }

    /**
     * Rules of one room (SRS 7.2, 7.3): stay length, capacity, no child without an adult, no empty room,
     * children policy, extra bed and cot limits.
     */
    private function validateRoom(Room $room, RoomAllocationData $allocation, int $index, RoomQuoteRequestData $request, int $nights): void
    {
        $prefix = $request->multiRoom ? "rooms.{$index}." : '';
        $subject = $request->multiRoom ? $this->subject($index, $request) : __('allocation.this_room');
        $short = $request->multiRoom ? $subject : __('allocation.room');
        $childrenKey = $request->multiRoom ? $prefix.'children_ages' : 'children';
        $policy = $room->childPolicy ?? $room->building->childPolicy;

        if ($nights >= 1 && $nights < $room->min_nights) {
            $this->addError('check_out', __('allocation.min_stay', ['room' => $subject, 'nights' => $room->min_nights]));
        } elseif ($room->max_nights !== null && $nights > $room->max_nights) {
            $this->addError('check_out', __('allocation.max_stay', ['room' => $subject, 'nights' => $room->max_nights]));
        }

        if ($allocation->guests() === 0) {
            $this->addError($prefix.'adults', __('allocation.empty_room', ['room' => $subject]));

            return;
        }

        if ($allocation->adults < 1) {
            $this->addError($prefix.'adults', __('allocation.child_without_adult'));
        }

        if ($allocation->adults > $room->max_adults) {
            $this->addError($prefix.'adults', __('allocation.max_adults', ['room' => $subject, 'max' => $room->max_adults]));
        }

        if ($allocation->children() > $room->max_children) {
            $this->addError($childrenKey, __('allocation.max_children', ['room' => $subject, 'max' => $room->max_children]));
        }

        if ($allocation->guests() > $room->capacityWith($allocation->extraBeds)) {
            $this->addError($prefix.'adults', __('allocation.capacity', ['room' => $short, 'max' => $room->capacityWith($allocation->extraBeds)]));
        }

        if ($allocation->children() > 0 && $policy !== null) {
            if (! $policy->children_allowed) {
                $this->addError($childrenKey, __('allocation.children_not_accepted', ['room' => $subject]));
            } elseif ($policy->min_child_age !== null && min($allocation->childAges) < $policy->min_child_age) {
                $this->addError($childrenKey, __('allocation.min_child_age', ['room' => $subject, 'age' => $policy->min_child_age]));
            }
        }

        if ($allocation->extraBeds > $room->max_extra_beds) {
            $this->addError($prefix.'extra_beds', __('allocation.max_extra_beds', ['room' => $subject, 'max' => $room->max_extra_beds]));
        }

        if ($allocation->cots > $room->max_cots) {
            $this->addError($prefix.'cots', __('allocation.max_cots', ['room' => $subject, 'max' => $room->max_cots]));
        }
    }

    private function subject(int $index, RoomQuoteRequestData $request): string
    {
        return $request->multiRoom ? __('allocation.room_number', ['number' => $index + 1]) : __('allocation.this_room');
    }

    private function roomLabel(Room $room, int $index, RoomQuoteRequestData $request): string
    {
        return $request->multiRoom ? 'Room '.($index + 1).' · '.$room->name : (string) $room->name;
    }

    /**
     * Night price for a per-person room: adults at the nightly rate, children by age band.
     *
     * @param  list<int>  $childAges
     */
    private function perPersonNight(float $nightPrice, int $adults, array $childAges, ?ChildPolicy $policy): float
    {
        $total = $nightPrice * $adults;
        $discounted = 0;

        foreach ($childAges as $age) {
            $allowed = $policy?->max_discounted_children === null || $discounted < $policy->max_discounted_children;
            $price = $this->guestNightPrice($nightPrice, $age, $policy, $allowed);

            if ($price < $nightPrice) {
                $discounted++;
            }

            $total += $price;
        }

        return round($total, 2);
    }
}
