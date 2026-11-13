<?php

namespace App\Services\Pricing;

use App\DTOs\Pricing\QuoteLineData;
use App\DTOs\Pricing\QuoteRequestData;
use App\DTOs\Pricing\QuoteResultData;
use App\Enums\BookingType;
use App\Enums\CommissionCategory;
use App\Enums\PricingMode;
use App\Models\ChildAgeBand;
use App\Models\Partner;
use App\Models\SeasonPrice;
use App\Models\Trip;
use App\Models\TripPrivateTier;
use App\Models\TripUnitOption;
use App\Services\AvailabilityService;
use App\Services\CurrencyService;
use App\Services\PartnerPricingService;
use App\Services\SeasonPriceService;

/**
 * Server-side price calculation for trips and Nile trips (SRS 3.4.1, 3.5, 3.8, 8, 10.1).
 */
class TripPricingEngine
{
    use BuildsQuotes;

    public function __construct(
        private readonly SeasonPriceService $seasons,
        private readonly AvailabilityService $availability,
        private readonly PartnerPricingService $partnerPricing,
        private readonly CurrencyService $currencies,
    ) {}

    public function quote(Trip $trip, QuoteRequestData $request): QuoteResultData
    {
        $trip->loadMissing(['unitOptions', 'privateTiers', 'availabilityRules', 'childPolicy.bands', 'addons', 'seasonPrices']);

        $this->resetErrors();
        $category = $trip->type->commissionCategory();
        $partner = $request->partner?->isActive() ? $request->partner : null;
        $persons = $request->persons();

        $this->validateParty($trip, $request);

        $availabilityError = $this->availability->check($trip, $request->bookingType, $request->travelDate);

        if ($availabilityError !== null) {
            $this->addError($trip->supportsBookingType($request->bookingType) ? 'travel_date' : 'booking_type', $availabilityError);
        }

        $season = $this->seasons->forDate($trip, $request->travelDate);

        $lines = $trip->pricing_mode === PricingMode::PerUnit
            ? $this->unitLines($trip, $request, $season, $partner, $category)
            : $this->personLines($trip, $request, $season, $partner, $category);

        $unitCount = array_sum(array_map(fn (QuoteLineData $line): int => $line->quantity, array_filter($lines, fn (QuoteLineData $line): bool => $line->type === QuoteLineData::TYPE_UNIT)));

        $lines = [...$lines, ...$this->addonLines($trip, $request, $persons, $unitCount, $partner, $category)];

        return $this->buildResult(
            serviceType: $trip->type->serviceType(),
            lines: $lines,
            seasonName: $season?->name,
            partner: $partner,
            depositPercent: (float) $trip->deposit_percent,
            currency: $request->currency,
            context: [
                'trip_id' => $trip->id,
                'pricing_mode' => $trip->pricing_mode->value,
                'booking_type' => $request->bookingType->value,
                'travel_date' => $request->travelDate->toDateString(),
                'adults' => $request->adults,
                'children' => $request->children,
                'persons' => $persons,
            ],
        );
    }

    private function validateParty(Trip $trip, QuoteRequestData $request): void
    {
        $persons = $request->persons();

        if ($request->adults < 1) {
            $this->addError('adults', 'At least one adult must travel.');
        }

        if ($request->children > 0 && count($request->childAges) !== $request->children) {
            $this->addError('child_ages', 'Enter the age of every child.');
        }

        if ($request->children > 0 && $trip->childPolicy !== null && ! $trip->childPolicy->children_allowed) {
            $this->addError('children', 'Children cannot take part in this trip.');
        }

        if ($persons < $trip->min_persons) {
            $this->addError('adults', "This trip needs at least {$trip->min_persons} persons per booking.");
        }

        if ($trip->max_persons !== null && $persons > $trip->max_persons) {
            $this->addError('adults', "This trip allows at most {$trip->max_persons} persons per booking.");
        }
    }

    /**
     * Per-person pricing: private party-size tier or group adult/child price, children discounted by age band.
     *
     * @return list<QuoteLineData>
     */
    private function personLines(Trip $trip, QuoteRequestData $request, ?SeasonPrice $season, ?Partner $partner, CommissionCategory $category): array
    {
        $persons = $request->persons();

        if ($request->bookingType === BookingType::Private) {
            $tier = $trip->privateTiers->first(fn (TripPrivateTier $tier): bool => $tier->covers($persons));

            if ($tier === null) {
                $this->addError('adults', "No private price is available for a party of {$persons}.");

                return [];
            }

            $seasonTierPrice = $season?->tierPriceFor($tier->id);
            $adultPrice = $seasonTierPrice ?? (float) $tier->price_per_person;
            $adultPartnerPrice = $seasonTierPrice !== null ? $season->tierPartnerPriceFor($tier->id) : $this->nullableFloat($tier->partner_price);
            $childPrice = $adultPrice;
            $adultLabel = "Private, party of {$tier->label()} (per person)";
        } else {
            $tier = null;
            $seasonAdultPrice = $season?->adult_price !== null ? (float) $season->adult_price : null;
            $adultPrice = $seasonAdultPrice ?? $this->nullableFloat($trip->base_price);

            if ($adultPrice === null) {
                $this->addError('booking_type', 'No group price is set for this trip.');

                return [];
            }

            $adultPartnerPrice = $seasonAdultPrice !== null ? $this->nullableFloat($season->partner_price) : $this->nullableFloat($trip->partner_price);
            $childPrice = $this->nullableFloat($season?->child_price) ?? $this->nullableFloat($trip->base_child_price) ?? $adultPrice;
            $adultLabel = 'Adult';
        }

        $policy = $trip->childPolicy;
        $adultQuantity = $request->adults;
        $childGroups = [];
        $discountedChildren = 0;

        foreach (array_slice($request->childAges, 0, $request->children) as $age) {
            if ($policy !== null && $age >= $policy->adult_from_age) {
                $adultQuantity++;

                continue;
            }

            $band = $policy?->bandForAge($age);
            $discountAllowed = $policy?->max_discounted_children === null || $discountedChildren < $policy->max_discounted_children;

            if ($band instanceof ChildAgeBand && $discountAllowed) {
                $discountedChildren++;
                $key = 'band-'.$band->id;
                $childGroups[$key] ??= [
                    'label' => 'Child '.$band->label(),
                    'price' => $band->applyTo($adultPrice),
                    'partner' => $adultPartnerPrice !== null ? $band->applyTo($adultPartnerPrice) : null,
                    'quantity' => 0,
                    'meta' => ['child_age_band_id' => $band->id, 'age' => $age, 'ages' => []],
                ];
                $childGroups[$key]['quantity']++;
                $childGroups[$key]['meta']['ages'][] = $age;

                continue;
            }

            $childGroups['child'] ??= ['label' => 'Child', 'price' => $childPrice, 'partner' => null, 'quantity' => 0, 'meta' => ['age' => $age, 'ages' => []]];
            $childGroups['child']['quantity']++;
            $childGroups['child']['meta']['ages'][] = $age;
        }

        if ($request->children > 0 && $request->childAges === []) {
            $childGroups['child'] = ['label' => 'Child', 'price' => $childPrice, 'partner' => null, 'quantity' => $request->children, 'meta' => []];
        }

        $lines = [];

        if ($adultQuantity > 0) {
            $lines[] = $this->line(QuoteLineData::TYPE_ADULT, $adultLabel, $adultQuantity, $adultPrice, $adultPartnerPrice, $partner, $category, [
                'private_tier_id' => $tier?->id,
            ]);
        }

        foreach ($childGroups as $group) {
            $lines[] = $this->line(QuoteLineData::TYPE_CHILD, $group['label'], $group['quantity'], $group['price'], $group['partner'], $partner, $category, $group['meta']);
        }

        return $lines;
    }

    /**
     * Per-unit pricing: each selected unit option x quantity, with seat and adult-per-unit checks.
     *
     * @return list<QuoteLineData>
     */
    private function unitLines(Trip $trip, QuoteRequestData $request, ?SeasonPrice $season, ?Partner $partner, CommissionCategory $category): array
    {
        if ($request->units === []) {
            $this->addError('units', 'Choose at least one unit.');

            return [];
        }

        $persons = $request->persons();
        $lines = [];
        $seats = 0;
        $units = 0;
        $childrenAllowed = false;

        foreach ($request->units as $selection) {
            /** @var TripUnitOption|null $option */
            $option = $trip->unitOptions->firstWhere('id', $selection->unitOptionId);

            if ($option === null) {
                $this->addError('units', "Unit option #{$selection->unitOptionId} is not available for this trip.");

                continue;
            }

            if ($selection->quantity < 1) {
                continue;
            }

            if ($selection->quantity > $option->max_quantity) {
                $this->addError('units', "You can book at most {$option->max_quantity} × {$option->name}.");
            }

            $seats += $option->seats * $selection->quantity;
            $units += $selection->quantity;
            $childrenAllowed = $childrenAllowed || $option->children_allowed;

            $seasonPrice = $season?->unitPriceFor($option->id);
            $price = $seasonPrice ?? (float) $option->price;
            $explicitPartnerPrice = $seasonPrice !== null ? $season->unitPartnerPriceFor($option->id) : $this->nullableFloat($option->partner_price);

            $lines[] = $this->line(QuoteLineData::TYPE_UNIT, (string) $option->name, $selection->quantity, $price, $explicitPartnerPrice, $partner, $category, [
                'unit_option_id' => $option->id,
                'seats' => $option->seats,
            ]);
        }

        if ($units === 0) {
            $this->addError('units', 'Choose at least one unit.');

            return $lines;
        }

        if ($seats < $persons) {
            $this->addError('units', "The selected units have {$seats} seats for {$persons} travellers. Add another unit.");
        }

        if ($units > $persons) {
            $this->addError('units', "Every unit needs at least one traveller: {$units} units for {$persons} travellers.");
        } elseif ($request->adults < $units) {
            $this->addError('units', "Every unit needs at least one adult: {$units} units but only {$request->adults} adults.");
        }

        if ($request->children > 0 && ! $childrenAllowed) {
            $this->addError('children', 'Children are not allowed on the selected units.');
        }

        return $lines;
    }

    /**
     * @return list<QuoteLineData>
     */
    private function addonLines(Trip $trip, QuoteRequestData $request, int $persons, int $unitCount, ?Partner $partner, CommissionCategory $category): array
    {
        return $this->productAddonLines($trip->addons, $request, $persons, $unitCount, $partner, $category);
    }
}
