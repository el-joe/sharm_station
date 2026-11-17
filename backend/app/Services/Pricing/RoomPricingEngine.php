<?php

namespace App\Services\Pricing;

use App\DTOs\Pricing\QuoteLineData;
use App\DTOs\Pricing\QuoteResultData;
use App\DTOs\Pricing\RoomQuoteRequestData;
use App\Enums\RoomPricingMode;
use App\Enums\ServiceType;
use App\Models\ChildPolicy;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Services\CurrencyService;
use App\Services\NationalityService;
use Carbon\CarbonImmutable;

/**
 * Accommodation stay pricing (SRS 6.6, 7.4, 3.8, 3.9): each night is priced on its own — a date override,
 * else the nationality/season matrix (group season → all-groups season → group base → room base).
 * No partner commission on accommodation.
 */
class RoomPricingEngine
{
    use BuildsQuotes;

    public function __construct(
        private readonly PriceResolver $prices,
        private readonly CommissionResolver $commissions,
        private readonly NationalityService $nationalities,
        private readonly CurrencyService $currencies,
    ) {}

    public function quote(Room $room, RoomQuoteRequestData $request): QuoteResultData
    {
        $room->loadMissing(['building.childPolicy.bands', 'childPolicy.bands', 'seasonPrices', 'productPrices']);

        // Accommodation has no partner commission (CR-11): partners pay the customer price.
        $this->beginQuote($request->partner, null, $request->nationalityGroupId, $request->nationalityCode);
        $building = $room->building;
        $nights = $request->nights();

        $this->validateStay($room, $request, $nights);

        $availability = $room->availability()
            ->whereBetween('date', [$request->checkIn->toDateString(), $request->checkIn->addDays(max($nights, 1) - 1)->toDateString()])
            ->get()
            ->keyBy(fn (RoomAvailability $day): string => $day->date->toDateString());

        $policy = $room->childPolicy ?? $building->childPolicy;
        $lines = [];
        $seasonNames = [];

        for ($night = $request->checkIn; $night->lt($request->checkOut); $night = $night->addDay()) {
            /** @var RoomAvailability|null $day */
            $day = $availability->get($night->toDateString());

            if ($day?->blocksBooking()) {
                $this->addError('check_in', "This room is not available on {$night->format('j M Y')}.");
            }

            $prices = $day?->price_override === null ? $this->prices->resolve($room, $this->nationalityGroup?->id, $night) : null;
            $season = $prices?->season();
            $nightPrice = $this->nullableFloat($day?->price_override) ?? $prices?->night() ?? (float) $room->base_price;

            if ($season !== null) {
                $seasonNames[] = (string) $season->name;
            }

            $unitPrice = $room->pricing_mode === RoomPricingMode::PerPerson
                ? $this->perPersonNight($nightPrice, $request, $policy)
                : $nightPrice;

            $lines[] = $this->line(
                QuoteLineData::TYPE_NIGHT,
                $night->format('D j M Y').($season !== null ? ' · '.$season->name : ''),
                1,
                $unitPrice,
                [
                    'date' => $night->toDateString(),
                    'season' => $season?->name,
                    'price_per_night' => $nightPrice,
                    'price_source' => $day?->price_override !== null ? 'date_override' : $prices?->source('price_per_night')?->value,
                ],
            );
        }

        if (! $building->taxes_included && $building->tax_per_night !== null && $nights > 0) {
            $lines[] = $this->line(QuoteLineData::TYPE_TAX, 'Taxes and city fees', $nights, (float) $building->tax_per_night, [], commissionable: false);
        }

        $seasonNames = array_values(array_unique($seasonNames));

        return $this->buildResult(
            serviceType: ServiceType::Accommodation,
            lines: $lines,
            seasonName: $seasonNames === [] ? null : implode(', ', $seasonNames),
            depositPercent: (float) $building->deposit_percent,
            currency: $request->currency,
            context: [
                'room_id' => $room->id,
                'pricing_mode' => $room->pricing_mode->value,
                'check_in' => $request->checkIn->toDateString(),
                'check_out' => $request->checkOut->toDateString(),
                'nights' => max($nights, 0),
                'adults' => $request->adults,
                'children' => $request->children,
                'tax_included' => (bool) $building->taxes_included,
                'tax_per_night' => $building->taxes_included ? null : $this->nullableFloat($building->tax_per_night),
            ],
        );
    }

    private function validateStay(Room $room, RoomQuoteRequestData $request, int $nights): void
    {
        if ($nights < 1) {
            $this->addError('check_out', 'Check-out must be after check-in.');
        } elseif ($nights < $room->min_nights) {
            $this->addError('check_out', "This room requires a minimum stay of {$room->min_nights} nights.");
        } elseif ($room->max_nights !== null && $nights > $room->max_nights) {
            $this->addError('check_out', "This room allows a maximum stay of {$room->max_nights} nights.");
        }

        if ($request->checkIn->lt(CarbonImmutable::today())) {
            $this->addError('check_in', 'Check-in cannot be in the past.');
        }

        if ($request->adults < 1) {
            $this->addError('adults', 'A child cannot stay in a room without an adult.');
        }

        if ($request->adults > $room->max_adults) {
            $this->addError('adults', "This room sleeps at most {$room->max_adults} adults.");
        }

        if ($request->children > $room->max_children) {
            $this->addError('children', "This room accepts at most {$room->max_children} children.");
        }

        if ($request->adults + $request->children > $room->max_total) {
            $this->addError('adults', "Room exceeds its capacity of {$room->max_total}.");
        }

        if ($request->children > 0 && count($request->childAges) !== $request->children) {
            $this->addError('child_ages', 'Enter the age of every child.');
        }
    }

    /**
     * Night price for per-person rooms: adults at the nightly rate, children by age band.
     */
    private function perPersonNight(float $nightPrice, RoomQuoteRequestData $request, ?ChildPolicy $policy): float
    {
        $retail = $nightPrice * $request->adults;
        $discounted = 0;

        foreach (array_slice($request->childAges, 0, $request->children) as $age) {
            $band = $policy !== null && $age < $policy->adult_from_age ? $policy->bandForAge($age) : null;
            $canDiscount = $policy?->max_discounted_children === null || $discounted < $policy->max_discounted_children;

            if ($band !== null && $canDiscount) {
                $discounted++;
                $retail += $band->applyTo($nightPrice);
            } else {
                $retail += $nightPrice;
            }
        }

        return round($retail, 2);
    }
}
