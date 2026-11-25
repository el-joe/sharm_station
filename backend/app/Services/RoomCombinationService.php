<?php

namespace App\Services;

use App\DTOs\Room\RoomCombinationQueryData;
use App\Models\Building;
use App\Models\ChildPolicy;
use App\Models\Room;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Services\Pricing\RoomPricingEngine;
use Illuminate\Support\Collection;

/**
 * "Best room combinations" helper (SRS 6.4): for a party and a stay in one building, the cheapest sets of free
 * rooms that hold everybody under the allocation rules of SRS 7.2 (each room ≥ 1 adult, capacity per room,
 * no empty room), each with a suggested allocation and the total price from the RoomPricingEngine.
 *
 * Extra beds are used within each room's limit when they make a combination possible or cheaper: each bed adds one
 * place to the room's total capacity (never to its adults or children limits) and is priced per night as in quotes
 * (RoomPricingEngine::stayTotal). Cots add no place and the rooms have no infant rule that requires one, so the
 * suggestions never add cots (always 0; the guest can add them in the quote).
 *
 * Identical rooms (same type, capacity, extra beds, pricing and stay price) are grouped so a building with many
 * equal rooms does not flood the list with the same combination.
 */
class RoomCombinationService
{
    public const MAX_ROOMS = 5;

    public const MAX_ROOM_TYPES = 15;

    /**
     * Upper bound of fitting combinations evaluated per request (keeps large parties fast).
     */
    public const MAX_EVALUATED = 2000;

    /**
     * @var array<string, float>
     */
    private array $stayTotals = [];

    public function __construct(
        private readonly RoomRepositoryInterface $rooms,
        private readonly RoomPricingEngine $engine,
        private readonly CurrencyService $currencies,
        private readonly NationalityService $nationalities,
    ) {}

    /**
     * @return list<array<string, mixed>> ranked by total price, then number of rooms
     */
    public function best(Building $building, RoomCombinationQueryData $query): array
    {
        $groupId = $this->nationalities->resolveGroupId($query->nationalityGroupId);
        $types = $this->roomTypes($this->rooms->freeInBuilding($building->id, $query->checkIn, $query->checkOut), $query, $groupId);
        $party = $query->adults + count($query->childAges);
        $maxRooms = min(self::MAX_ROOMS, $query->adults, $party);
        $results = [];
        $evaluated = 0;
        $this->stayTotals = [];

        foreach ($this->multisets(count($types), $maxRooms, array_column($types, 'count')) as $pick) {
            $rooms = [];

            foreach ($pick as $typeIndex => $count) {
                array_push($rooms, ...array_slice($types[$typeIndex]['rooms'], 0, $count));
            }

            if (array_sum(array_map(fn (array $room): int => $room['room']->capacityWith($room['room']->max_extra_beds), $rooms)) < $party
                || array_sum(array_map(fn (array $room): int => $room['room']->max_adults, $rooms)) < $query->adults) {
                continue;
            }

            if (++$evaluated > self::MAX_EVALUATED) {
                break;
            }

            $allocation = $this->cheapestAllocation($rooms, $query);

            if ($allocation !== null) {
                $results[] = $this->present($rooms, $allocation, $building, $query);
            }
        }

        usort($results, fn (array $a, array $b): int => [$a['total_eur'], $a['rooms_count'], $a['extra_beds']] <=> [$b['total_eur'], $b['rooms_count'], $b['extra_beds']]);

        return array_slice($results, 0, $query->limit);
    }

    /**
     * Free rooms grouped by identical offer, cheapest first.
     *
     * @param  Collection<int, Room>  $rooms
     * @return list<array{count: int, rooms: list<array{room: Room, nights: list<array{price: float}>}>}>
     */
    private function roomTypes(Collection $rooms, RoomCombinationQueryData $query, int $groupId): array
    {
        $types = [];

        foreach ($rooms as $room) {
            $nights = $this->engine->nightlyPrices($room, $query->checkIn, $query->checkOut, $groupId);

            if (collect($nights)->contains('blocked', true)) {
                continue;
            }

            $policy = $room->childPolicy ?? $room->building->childPolicy;
            $key = implode('|', [$room->room_type->value, $room->max_adults, $room->max_children, $room->max_total, $room->pricing_mode->value,
                $policy?->id, array_sum(array_column($nights, 'price')), $room->max_extra_beds, (float) $room->extra_bed_price]);
            $types[$key] ??= ['count' => 0, 'rooms' => [], 'base' => $this->engine->stayTotal($room, $nights, 1, [])];
            $types[$key]['count']++;
            $types[$key]['rooms'][] = ['room' => $room, 'nights' => $nights];
        }

        uasort($types, fn (array $a, array $b): int => $a['base'] <=> $b['base']);

        return array_values(array_slice($types, 0, self::MAX_ROOM_TYPES));
    }

    /**
     * Every multiset of 1..$max room types (as typeIndex => count), respecting how many rooms each type has.
     *
     * @param  list<int>  $available
     * @return iterable<array<int, int>>
     */
    private function multisets(int $typeCount, int $max, array $available, int $from = 0, array $current = [], int $size = 0): iterable
    {
        if ($size > 0) {
            yield $current;
        }

        if ($size === $max) {
            return;
        }

        for ($type = $from; $type < $typeCount; $type++) {
            if (($current[$type] ?? 0) >= $available[$type]) {
                continue;
            }

            $next = $current;
            $next[$type] = ($next[$type] ?? 0) + 1;

            yield from $this->multisets($typeCount, $max, $available, $type, $next, $size + 1);
        }
    }

    /**
     * Cheapest valid split of the party across the rooms: every room gets at least one adult and stays within its
     * adults and children limits and its total capacity plus its extra beds (one bed per guest above max_total);
     * children (oldest first) go to the rooms in order.
     *
     * @param  list<array{room: Room, nights: list<array{price: float}>}>  $rooms
     * @return array{total: float, rooms: list<array{adults: int, children_ages: list<int>, extra_beds: int, total: float}>}|null
     */
    private function cheapestAllocation(array $rooms, RoomCombinationQueryData $query): ?array
    {
        $ages = $query->childAges;
        rsort($ages);
        $best = null;

        $search = function (int $index, int $adultsLeft, int $childrenLeft, array $split) use (&$search, &$best, $rooms, $ages): void {
            if ($index === count($rooms)) {
                if ($adultsLeft !== 0 || $childrenLeft !== 0) {
                    return;
                }

                $allocation = $this->priceSplit($rooms, $split, $ages);

                if ($allocation !== null && ($best === null || $allocation['total'] < $best['total'])) {
                    $best = $allocation;
                }

                return;
            }

            $room = $rooms[$index]['room'];
            $roomsLeft = count($rooms) - $index - 1;

            for ($adults = 1; $adults <= min($room->max_adults, $adultsLeft - $roomsLeft); $adults++) {
                for ($children = 0; $children <= min($room->max_children, $room->capacityWith($room->max_extra_beds) - $adults, $childrenLeft); $children++) {
                    $search($index + 1, $adultsLeft - $adults, $childrenLeft - $children, [...$split, [$adults, $children]]);
                }
            }
        };

        $search(0, $query->adults, count($ages), []);

        return $best;
    }

    /**
     * @param  list<array{room: Room, nights: list<array{price: float}>}>  $rooms
     * @param  list<array{0: int, 1: int}>  $split
     * @param  list<int>  $ages  oldest first
     * @return array{total: float, rooms: list<array{adults: int, children_ages: list<int>, extra_beds: int, total: float}>}|null
     */
    private function priceSplit(array $rooms, array $split, array $ages): ?array
    {
        $total = 0.0;
        $allocated = [];

        foreach ($rooms as $index => ['room' => $room, 'nights' => $nights]) {
            [$adults, $children] = $split[$index];
            $roomAges = array_splice($ages, 0, $children);

            if ($roomAges !== [] && ! $this->acceptsChildren($room->childPolicy ?? $room->building->childPolicy, $roomAges)) {
                return null;
            }

            $extraBeds = max(0, $adults + $children - $room->max_total);
            $roomTotal = $this->stayTotals[$room->id.'|'.$adults.'|'.implode(',', $roomAges)] ??= $this->engine->stayTotal($room, $nights, $adults, $roomAges, $extraBeds);
            $building = $room->building;

            if (! $building->taxes_included && $building->tax_per_night !== null) {
                $roomTotal += count($nights) * (float) $building->tax_per_night;
            }

            $total += $roomTotal;
            $allocated[] = ['adults' => $adults, 'children_ages' => $roomAges, 'extra_beds' => $extraBeds, 'total' => round($roomTotal, 2)];
        }

        return ['total' => round($total, 2), 'rooms' => $allocated];
    }

    /**
     * @param  list<int>  $ages
     */
    private function acceptsChildren(?ChildPolicy $policy, array $ages): bool
    {
        return $policy === null || ($policy->children_allowed && ($policy->min_child_age === null || min($ages) >= $policy->min_child_age));
    }

    /**
     * @param  list<array{room: Room, nights: list<array{price: float}>}>  $rooms
     * @param  array{total: float, rooms: list<array{adults: int, children_ages: list<int>, extra_beds: int, total: float}>}  $allocation
     * @return array<string, mixed>
     */
    private function present(array $rooms, array $allocation, Building $building, RoomCombinationQueryData $query): array
    {
        $items = [];

        foreach ($rooms as $index => ['room' => $room]) {
            $items[] = [
                'room_id' => $room->id,
                'name' => (string) $room->name,
                'number' => $room->number,
                'room_type' => $room->room_type->value,
                'pricing_mode' => $room->pricing_mode->value,
                'capacity' => ['max_adults' => $room->max_adults, 'max_children' => $room->max_children, 'max_total' => $room->max_total,
                    'max_extra_beds' => $room->max_extra_beds, 'max_cots' => $room->max_cots],
                'adults' => $allocation['rooms'][$index]['adults'],
                'children_ages' => $allocation['rooms'][$index]['children_ages'],
                'extra_beds' => $allocation['rooms'][$index]['extra_beds'],
                'cots' => 0,
                'total' => $this->currencies->money($allocation['rooms'][$index]['total']),
            ];
        }

        return [
            'rooms_count' => count($items),
            'capacity' => array_sum(array_map(fn (array $room): int => $room['room']->max_total, $rooms)),
            'extra_beds' => array_sum(array_column($items, 'extra_beds')),
            'total_eur' => $allocation['total'],
            'total' => $this->currencies->money($allocation['total']),
            'tax_included' => (bool) $building->taxes_included,
            'rooms' => $items,
            'request' => [
                'check_in' => $query->checkIn->toDateString(),
                'check_out' => $query->checkOut->toDateString(),
                'rooms' => array_map(fn (array $item): array => [
                    'room_id' => $item['room_id'],
                    'adults' => $item['adults'],
                    'children_ages' => $item['children_ages'],
                    'extra_beds' => $item['extra_beds'],
                    'cots' => $item['cots'],
                ], $items),
            ],
        ];
    }
}
