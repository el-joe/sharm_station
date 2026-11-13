<?php

namespace App\Services\Pricing;

use App\DTOs\Pricing\QuoteLineData;
use App\DTOs\Pricing\QuoteResultData;
use App\DTOs\Pricing\RoomQuoteRequestData;
use App\Enums\CommissionCategory;
use App\Enums\RoomPricingMode;
use App\Enums\ServiceType;
use App\Models\ChildPolicy;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Services\CurrencyService;
use App\Services\PartnerPricingService;
use App\Services\SeasonPriceService;
use Carbon\CarbonImmutable;

/**
 * Accommodation stay pricing (SRS 6.6, 7.4, 3.8): each night is priced by its own season
 * (price_per_night), a date override, or the room base price.
 */
class RoomPricingEngine
{
    use BuildsQuotes;

    public function __construct(
        private readonly SeasonPriceService $seasons,
        private readonly PartnerPricingService $partnerPricing,
        private readonly CurrencyService $currencies,
    ) {}

    public function quote(Room $room, RoomQuoteRequestData $request): QuoteResultData
    {
        $room->loadMissing(['building.childPolicy.bands', 'childPolicy.bands', 'seasonPrices']);

        $this->resetErrors();
        $partner = $request->partner?->isActive() ? $request->partner : null;
        $category = CommissionCategory::Accommodation;
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

            $season = $day?->price_override === null ? $this->seasons->forDate($room, $night) : null;
            $nightPrice = $this->nullableFloat($day?->price_override) ?? $this->nullableFloat($season?->price_per_night) ?? (float) $room->base_price;
            $explicitPartnerPrice = match (true) {
                $day?->price_override !== null => null,
                $season?->price_per_night !== null => $this->nullableFloat($season->partner_price),
                default => $this->nullableFloat($room->partner_price),
            };

            if ($season !== null) {
                $seasonNames[] = (string) $season->name;
            }

            [$unitPrice, $partnerUnitPrice] = $room->pricing_mode === RoomPricingMode::PerPerson
                ? $this->perPersonNight($nightPrice, $explicitPartnerPrice, $request, $policy)
                : [$nightPrice, $explicitPartnerPrice];

            $lines[] = $this->line(
                QuoteLineData::TYPE_NIGHT,
                $night->format('D j M Y').($season !== null ? ' · '.$season->name : ''),
                1,
                $unitPrice,
                $partnerUnitPrice,
                $partner,
                $category,
                ['date' => $night->toDateString(), 'season' => $season?->name, 'price_per_night' => $nightPrice],
            );
        }

        if (! $building->taxes_included && $building->tax_per_night !== null && $nights > 0) {
            $lines[] = $this->line(QuoteLineData::TYPE_TAX, 'Taxes and city fees', $nights, (float) $building->tax_per_night, null, $partner, $category, [], commissionable: false);
        }

        $seasonNames = array_values(array_unique($seasonNames));

        return $this->buildResult(
            serviceType: ServiceType::Accommodation,
            lines: $lines,
            seasonName: $seasonNames === [] ? null : implode(', ', $seasonNames),
            partner: $partner,
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
     *
     * @return array{0: float, 1: float|null}
     */
    private function perPersonNight(float $nightPrice, ?float $partnerNightPrice, RoomQuoteRequestData $request, ?ChildPolicy $policy): array
    {
        $retail = $nightPrice * $request->adults;
        $partner = $partnerNightPrice !== null ? $partnerNightPrice * $request->adults : null;
        $discounted = 0;

        foreach (array_slice($request->childAges, 0, $request->children) as $age) {
            $band = $policy !== null && $age < $policy->adult_from_age ? $policy->bandForAge($age) : null;
            $canDiscount = $policy?->max_discounted_children === null || $discounted < $policy->max_discounted_children;

            if ($band !== null && $canDiscount) {
                $discounted++;
                $retail += $band->applyTo($nightPrice);
                $partner = $partner !== null ? $partner + $band->applyTo((float) $partnerNightPrice) : null;
            } else {
                $retail += $nightPrice;
                $partner = $partner !== null ? $partner + (float) $partnerNightPrice : null;
            }
        }

        return [round($retail, 2), $partner !== null ? round($partner, 2) : null];
    }
}
