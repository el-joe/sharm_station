<?php

namespace App\Services\Pricing;

use App\DTOs\Pricing\QuoteLineData;
use App\DTOs\Pricing\QuoteRequestData;
use App\DTOs\Pricing\QuoteResultData;
use App\Enums\BookingType;
use App\Enums\CommissionCategory;
use App\Enums\PackagePriceMode;
use App\Enums\PricingMode;
use App\Enums\ServiceType;
use App\Models\ChildAgeBand;
use App\Models\ChildPolicy;
use App\Models\Package;
use App\Models\PackageIncludedTransfer;
use App\Models\PackageStage;
use App\Models\PackageStageTrip;
use App\Models\Partner;
use App\Models\Trip;
use App\Models\TripPrivateTier;
use App\Models\TripUnitOption;
use App\Services\CurrencyService;
use App\Services\PartnerPricingService;
use App\Services\SeasonPriceService;
use Carbon\CarbonImmutable;

/**
 * Package pricing (SRS 4.2): booked per person, private or group, with a fixed price (season by travel date)
 * or a price computed from the linked trips (each trip priced on the date its day falls on) minus a manual discount.
 * Transfers are always included and never charged.
 */
class PackagePricingEngine
{
    use BuildsQuotes;

    public function __construct(
        private readonly SeasonPriceService $seasons,
        private readonly PartnerPricingService $partnerPricing,
        private readonly CurrencyService $currencies,
    ) {}

    public function quote(Package $package, QuoteRequestData $request): QuoteResultData
    {
        $package->loadMissing([
            'stages.destination', 'stages.stageTrips.trip.privateTiers', 'stages.stageTrips.trip.unitOptions',
            'stages.stageTrips.trip.seasonPrices', 'includedTransfers', 'childPolicy.bands', 'addons', 'seasonPrices',
        ]);

        $this->resetErrors();
        $category = CommissionCategory::Packages;
        $partner = $request->partner?->isActive() ? $request->partner : null;
        $persons = $request->persons();

        $this->validateRequest($package, $request);

        $season = null;

        if ($package->price_mode === PackagePriceMode::Computed) {
            [$adultPrice, $childPrice] = $this->computedPrices($package, $request);
            $adultPartnerPrice = null;
            $adultLabel = $request->bookingType === BookingType::Private ? 'Adult (private)' : 'Adult';
        } else {
            $season = $this->seasons->forDate($package, $request->travelDate);
            $seasonAdultPrice = $this->nullableFloat($season?->adult_price);
            $adultPrice = $seasonAdultPrice ?? $this->nullableFloat($package->base_price);
            $adultPartnerPrice = $seasonAdultPrice !== null ? $this->nullableFloat($season->partner_price) : $this->nullableFloat($package->partner_price);
            $childPrice = $this->nullableFloat($season?->child_price) ?? $this->nullableFloat($package->base_child_price) ?? $adultPrice;
            $adultLabel = 'Adult';
        }

        $lines = [];

        if ($adultPrice === null) {
            $this->addError('booking_type', 'No price is set for this package.');
        } else {
            $lines = $this->personLines($request, $adultPrice, $adultPartnerPrice, (float) $childPrice, $adultLabel, $package->childPolicy, $partner, $category);
        }

        $lines = [...$lines, ...$this->productAddonLines($package->addons, $request, $persons, 0, $partner, $category, 'package')];

        return $this->buildResult(
            serviceType: ServiceType::Package,
            lines: $lines,
            seasonName: $season?->name,
            partner: $partner,
            depositPercent: (float) $package->deposit_percent,
            currency: $request->currency,
            context: [
                'package_id' => $package->id,
                'pricing_mode' => PricingMode::PerPerson->value,
                'price_mode' => $package->price_mode->value,
                'booking_type' => $request->bookingType->value,
                'travel_date' => $request->travelDate->toDateString(),
                'end_date' => $request->travelDate->addDays(max(1, $package->days) - 1)->toDateString(),
                'adults' => $request->adults,
                'children' => $request->children,
                'persons' => $persons,
                'transfers_included' => true,
                'included_transfers' => $this->includedTransfers($package, $request->travelDate),
            ],
        );
    }

    /**
     * Included-transfer tasks for a departure date (no price).
     *
     * @return list<array{day_number: int, date: string, time: string|null, from: string, to: string, vehicle_type: string|null}>
     */
    public function includedTransfers(Package $package, CarbonImmutable $travelDate): array
    {
        return $package->includedTransfers->map(fn (PackageIncludedTransfer $transfer): array => [
            'day_number' => $transfer->day_number,
            'date' => $travelDate->addDays(max(1, $transfer->day_number) - 1)->toDateString(),
            'time' => $transfer->time ? substr((string) $transfer->time, 0, 5) : null,
            'from' => $transfer->from_place,
            'to' => $transfer->to_place,
            'vehicle_type' => $transfer->vehicle_type?->value,
        ])->values()->all();
    }

    private function validateRequest(Package $package, QuoteRequestData $request): void
    {
        $persons = $request->persons();

        if (! $package->supportsBookingType($request->bookingType)) {
            $this->addError('booking_type', "{$request->bookingType->label()} bookings are not available for this package.");
        }

        if ($request->travelDate->lt(CarbonImmutable::today()->addDay())) {
            $this->addError('travel_date', 'Choose a departure date from tomorrow onwards.');
        }

        if ($request->adults < 1) {
            $this->addError('adults', 'At least one adult must travel.');
        }

        if ($request->children > 0 && count($request->childAges) !== $request->children) {
            $this->addError('child_ages', 'Enter the age of every child.');
        }

        if ($request->children > 0 && $package->childPolicy !== null && ! $package->childPolicy->children_allowed) {
            $this->addError('children', 'Children cannot take part in this package.');
        }

        if ($persons < $package->min_persons) {
            $this->addError('adults', "This package needs at least {$package->min_persons} persons per booking.");
        }

        if ($package->max_persons !== null && $persons > $package->max_persons) {
            $this->addError('adults', "This package allows at most {$package->max_persons} persons per booking.");
        }
    }

    /**
     * Sum of the linked trips' per-person prices on the date of their day, minus the package discount.
     *
     * @return array{0: float|null, 1: float|null}
     */
    private function computedPrices(Package $package, QuoteRequestData $request): array
    {
        $startDays = $package->stageStartDays();
        $adult = 0.0;
        $child = 0.0;
        $count = 0;

        foreach ($package->stages as $stage) {
            /** @var PackageStage $stage */
            foreach ($stage->stageTrips as $stageTrip) {
                /** @var PackageStageTrip $stageTrip */
                if ($stageTrip->trip === null) {
                    continue;
                }

                $date = $request->travelDate->addDays($startDays[$stage->id] + max(1, $stageTrip->day_number) - 2);
                [$tripAdult, $tripChild] = $this->tripPersonPrices($stageTrip->trip, $request->bookingType, $request->persons(), $date);
                $adult += $tripAdult;
                $child += $tripChild;
                $count++;
            }
        }

        if ($count === 0) {
            return [null, null];
        }

        $factor = 1 - ((float) $package->discount_percent) / 100;

        return [round($adult * $factor, 2), round($child * $factor, 2)];
    }

    /**
     * Per-person adult and child price of one trip on a date (private tier, group price or cheapest seat).
     *
     * @return array{0: float, 1: float}
     */
    private function tripPersonPrices(Trip $trip, BookingType $bookingType, int $persons, CarbonImmutable $date): array
    {
        $season = $this->seasons->forDate($trip, $date);

        if ($trip->pricing_mode === PricingMode::PerUnit) {
            $perSeat = $trip->unitOptions
                ->map(fn (TripUnitOption $option): float => ($season?->unitPriceFor($option->id) ?? (float) $option->price) / max(1, $option->seats))
                ->min();

            return [(float) $perSeat, (float) $perSeat];
        }

        if ($bookingType === BookingType::Private && $trip->private_enabled) {
            $tier = $trip->privateTiers->first(fn (TripPrivateTier $tier): bool => $tier->covers($persons));

            if ($tier !== null) {
                $price = $season?->tierPriceFor($tier->id) ?? (float) $tier->price_per_person;

                return [$price, $price];
            }
        }

        $adult = $this->nullableFloat($season?->adult_price) ?? $this->nullableFloat($trip->base_price) ?? (float) $trip->from_price;
        $child = $this->nullableFloat($season?->child_price) ?? $this->nullableFloat($trip->base_child_price) ?? $adult;

        return [$adult, $child];
    }

    /**
     * Adults at the adult price; children by the package's age bands, else the child price.
     *
     * @return list<QuoteLineData>
     */
    private function personLines(QuoteRequestData $request, float $adultPrice, ?float $adultPartnerPrice, float $childPrice, string $adultLabel, ?ChildPolicy $policy, ?Partner $partner, CommissionCategory $category): array
    {
        $adultQuantity = $request->adults;
        $childGroups = [];
        $discounted = 0;

        foreach (array_slice($request->childAges, 0, $request->children) as $age) {
            if ($policy !== null && $age >= $policy->adult_from_age) {
                $adultQuantity++;

                continue;
            }

            $band = $policy?->bandForAge($age);
            $discountAllowed = $policy?->max_discounted_children === null || $discounted < $policy->max_discounted_children;

            if ($band instanceof ChildAgeBand && $discountAllowed) {
                $discounted++;
                $key = 'band-'.$band->id;
                $childGroups[$key] ??= [
                    'label' => 'Child '.$band->label(),
                    'price' => $band->applyTo($adultPrice),
                    'partner' => $adultPartnerPrice !== null ? $band->applyTo($adultPartnerPrice) : null,
                    'quantity' => 0,
                    'meta' => ['child_age_band_id' => $band->id, 'age' => $age, 'ages' => []],
                ];
            } else {
                $key = 'child';
                $childGroups[$key] ??= ['label' => 'Child', 'price' => $childPrice, 'partner' => null, 'quantity' => 0, 'meta' => ['age' => $age, 'ages' => []]];
            }

            $childGroups[$key]['quantity']++;
            $childGroups[$key]['meta']['ages'][] = $age;
        }

        $lines = [];

        if ($adultQuantity > 0) {
            $lines[] = $this->line(QuoteLineData::TYPE_ADULT, $adultLabel, $adultQuantity, $adultPrice, $adultPartnerPrice, $partner, $category);
        }

        foreach ($childGroups as $group) {
            $lines[] = $this->line(QuoteLineData::TYPE_CHILD, $group['label'], $group['quantity'], $group['price'], $group['partner'], $partner, $category, $group['meta']);
        }

        return $lines;
    }
}
