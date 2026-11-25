<?php

namespace App\Services\Pricing;

use App\DTOs\Pricing\QuoteLineData;
use App\DTOs\Pricing\QuoteRequestData;
use App\DTOs\Pricing\RoomOptionSelectionData;
use App\Enums\RoomPricePeriod;
use App\Enums\RoomPricingMode;
use App\Models\ChildPolicy;
use App\Models\RoomOption;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Optional rooms of multi-day trips, Nile trips and package stages (SRS 7.1): priced add-ons with the allocation
 * rules of SRS 7.2 (every guest in exactly one room of the stage, capacity, no child alone, no empty room).
 * The customer price comes from the PriceResolver (nationality group + season of the stay's first night);
 * lines are commissionable, so partner quotes get the trip/package commission.
 *
 * Requires $prices (PriceResolver) and the BuildsQuotes trait on the using class.
 */
trait PricesRoomOptions
{
    /**
     * @param  Collection<int, RoomOption>  $options
     * @param  array<int, array{start: CarbonImmutable, nights: int, label: string|null}>  $stages  stage position (0 for trips) => stay
     * @return list<QuoteLineData>
     */
    private function roomOptionLines(Collection $options, QuoteRequestData $request, array $stages, ?ChildPolicy $policy, string $product): array
    {
        if ($request->roomOptions === []) {
            return [];
        }

        if ($stages === []) {
            $this->addError('room_options', __('allocation.options_multi_day_only'));

            return [];
        }

        $available = $options->filter(fn (RoomOption $option): bool => $option->is_active)->keyBy('id');
        $lines = [];
        $byStage = [];
        $quantities = [];

        foreach ($request->roomOptions as $index => $selection) {
            /** @var RoomOption|null $option */
            $option = $available->get($selection->roomOptionId);
            $stageKey = (int) ($option?->stage_position ?? 0);

            if ($option === null || ! isset($stages[$stageKey])) {
                $this->addError("room_options.{$index}.room_option_id", __('allocation.option_unavailable', ['id' => $selection->roomOptionId, 'product' => $product]));

                continue;
            }

            $quantities[$option->id] = ($quantities[$option->id] ?? 0) + 1;

            if ($quantities[$option->id] === $option->max_quantity + 1) {
                $this->addError("room_options.{$index}.room_option_id", __('allocation.option_quantity', ['max' => $option->max_quantity, 'name' => $option->label()]));
            }

            $this->validateRoomOption($option, $selection, $index);
            $byStage[$stageKey][] = [$option, $selection];
            $lines[] = $this->roomOptionLine($option, $selection, $stages[$stageKey], $stageKey, $policy);
        }

        foreach ($byStage as $stageKey => $selected) {
            $this->validateStageAllocation($request, $selected, $stageKey);
        }

        return $lines;
    }

    private function validateRoomOption(RoomOption $option, RoomOptionSelectionData $selection, int $index): void
    {
        $room = __('allocation.room_number', ['number' => $index + 1]);
        $prefix = "room_options.{$index}.";
        $children = count($selection->childAges);

        if ($selection->guests() === 0) {
            $this->addError($prefix.'adults', __('allocation.empty_room', ['room' => $room]));

            return;
        }

        if ($selection->adults < 1) {
            $this->addError($prefix.'adults', __('allocation.child_without_adult'));
        }

        if ($selection->adults > $option->max_adults) {
            $this->addError($prefix.'adults', __('allocation.max_adults', ['room' => $room, 'max' => $option->max_adults]));
        }

        if ($children > $option->max_children) {
            $this->addError($prefix.'children_ages', __('allocation.max_children', ['room' => $room, 'max' => $option->max_children]));
        }

        if ($selection->guests() > $option->max_total) {
            $this->addError($prefix.'adults', __('allocation.capacity', ['room' => $room, 'max' => $option->max_total]));
        }
    }

    /**
     * Every guest of the party sleeps in exactly one room of the stage.
     *
     * @param  list<array{0: RoomOption, 1: RoomOptionSelectionData}>  $selected
     */
    private function validateStageAllocation(QuoteRequestData $request, array $selected, int $stageKey): void
    {
        $party = $request->persons();
        $capacity = array_sum(array_map(fn (array $pair): int => $pair[0]->max_total, $selected));
        $adults = array_sum(array_map(fn (array $pair): int => $pair[1]->adults, $selected));
        $ages = array_merge([], ...array_map(fn (array $pair): array => $pair[1]->childAges, $selected));
        $expected = array_slice($request->childAges, 0, $request->children);
        sort($ages);
        sort($expected);

        if ($capacity < $party) {
            $this->addError('room_options', $stageKey > 0
                ? __('allocation.option_cannot_fit', ['stage' => $stageKey, 'count' => $party])
                : __('allocation.trip_cannot_fit', ['count' => $party]));
        } elseif ($adults !== $request->adults || $ages !== $expected) {
            $this->addError('room_options', $stageKey > 0
                ? __('allocation.option_stage', ['stage' => $stageKey])
                : __('allocation.trip_every_guest'));
        }
    }

    /**
     * Per room: the price for any occupancy within capacity; per person: adults at the price, children by the
     * product's age bands. Per night prices are multiplied by the stage nights.
     *
     * @param  array{start: CarbonImmutable, nights: int, label: string|null}  $stage
     */
    private function roomOptionLine(RoomOption $option, RoomOptionSelectionData $selection, array $stage, int $stageKey, ?ChildPolicy $policy): QuoteLineData
    {
        $resolved = $this->prices->resolve($option, $this->nationalityGroup?->id, $stage['start']);
        $price = $resolved->price('price') ?? (float) $option->price;
        $periods = $option->price_period === RoomPricePeriod::PerNight ? max(1, $stage['nights']) : 1;
        $guests = [];

        if ($option->pricing_mode === RoomPricingMode::PerPerson) {
            $perStay = $price * $selection->adults;
            $discounted = 0;

            foreach ($selection->childAges as $age) {
                $band = $policy !== null && $age < $policy->adult_from_age ? $policy->bandForAge($age) : null;
                $allowed = $policy?->max_discounted_children === null || $discounted < $policy->max_discounted_children;
                $childPrice = $band !== null && $allowed ? $band->applyTo($price) : $price;
                $discounted += $childPrice < $price ? 1 : 0;
                $perStay += $childPrice;
                $guests[] = ['type' => 'child', 'age' => $age, 'price' => round($childPrice * $periods, 2), 'discount' => round(($price - $childPrice) * $periods, 2)];
            }

            $unitPrice = $perStay * $periods;
        } else {
            $unitPrice = $price * $periods;
        }

        $occupants = $selection->adults.' ad.'.($selection->childAges !== [] ? ' + '.count($selection->childAges).' ch.' : '');

        return $this->line(QuoteLineData::TYPE_ROOM, $option->label().($stage['label'] !== null ? ' · '.$stage['label'] : '').' ('.$occupants.')', 1, $unitPrice, [
            'room_option_id' => $option->id,
            'stage_position' => $stageKey > 0 ? $stageKey : null,
            'unit_type' => $option->unit_type->value,
            'stars' => $option->stars,
            'pricing_mode' => $option->pricing_mode->value,
            'price_period' => $option->price_period->value,
            'price' => round($price, 2),
            'nights' => $stage['nights'],
            'start_date' => $stage['start']->toDateString(),
            'adults' => $selection->adults,
            'children_ages' => $selection->childAges,
            'guests' => $guests,
            'price_source' => $resolved->source('price')?->value,
        ]);
    }
}
