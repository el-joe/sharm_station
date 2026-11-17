<?php

namespace App\Services\Pricing;

use App\DTOs\Pricing\QuoteResultData;
use App\DTOs\Transfer\RouteData;
use App\DTOs\Transfer\TransferQuoteRequestData;
use App\Enums\CommissionCategory;
use App\Enums\ServiceType;
use App\Enums\TransferDirection;
use App\Models\TransferFixedRoute;
use App\Models\TransferHub;
use App\Models\VehicleType;
use App\Repositories\Contracts\TransferFixedRouteRepositoryInterface;
use App\Repositories\Contracts\TransferHubRepositoryInterface;
use App\Repositories\Contracts\VehicleTypeRepositoryInterface;
use App\Services\CurrencyService;
use App\Services\NationalityService;
use App\Services\Transfers\DistanceProvider;
use App\Services\Transfers\HaversineDistanceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Transfer price, computed on the server only (SRS 5.2):
 *
 *   P = max(M, (B + Σ d_i·R_i + W + S) · V) + F
 *
 * per leg, where a matching fixed route overrides the formula (its price comes from the nationality/season matrix),
 * the return leg of a round trip gets the hub's return discount, and selected extras are added once per booking.
 * Partner quotes apply the Transfers commission percentage (extras are not commissionable).
 */
class TransferPricingService
{
    use BuildsQuotes;

    public function __construct(
        private readonly TransferHubRepositoryInterface $hubs,
        private readonly VehicleTypeRepositoryInterface $vehicles,
        private readonly TransferFixedRouteRepositoryInterface $fixedRoutes,
        private readonly DistanceProvider $distances,
        private readonly PriceResolver $prices,
        private readonly CommissionResolver $commissions,
        private readonly NationalityService $nationalities,
        private readonly CurrencyService $currencies,
    ) {}

    public function quote(TransferQuoteRequestData $request): QuoteResultData
    {
        $this->beginQuote($request->partner, CommissionCategory::Transfers, $request->nationalityGroupId, $request->nationalityCode);
        $hub = $this->hubs->findEnabled($request->hubId);
        $vehicle = $this->vehicles->findEnabled($request->vehicleTypeId);

        if ($hub === null) {
            $this->addError('hub_id', 'This departure point is not available.');
        }

        if ($vehicle === null || ($hub !== null && ! $hub->allowsVehicle($vehicle))) {
            $this->addError('vehicle_type_id', 'This vehicle is not available from the selected departure point.');
        }

        if ($hub === null || $vehicle === null || $this->errors !== []) {
            return $this->buildResult(ServiceType::Transfer, [], null, 100, $request->currency, $this->baseContext($request));
        }

        $this->validateRequest($hub, $vehicle, $request);

        $route = $this->route($hub, $request);
        $fixedRoute = $this->matchingFixedRoute($hub, $vehicle, $request);

        if ($fixedRoute === null && $route->distanceKm > ($hub->max_distance_km ?? PHP_FLOAT_MAX)) {
            $this->addError('destination', "This destination is {$route->distanceKm} km away; the maximum for this departure point is {$hub->max_distance_km} km.");
        }

        $hubName = (string) $hub->name;
        [$fromLabel, $toLabel] = $request->direction === TransferDirection::FromHub
            ? [$hubName, $request->destinationName]
            : [$request->destinationName, $hubName];

        $legs = [];
        $lines = [];
        $outbound = $this->priceLeg($hub, $vehicle, $route, $fixedRoute, $request->pickupAt, $request->waitingMinutes);
        $legs[] = ['leg' => 'outbound', ...$outbound];
        $lines[] = $this->line('transfer', "{$fromLabel} → {$toLabel}", 1, $outbound['price'], [
            'leg' => 'outbound',
            'pickup_at' => $request->pickupAt->format('Y-m-d H:i'),
            'breakdown' => $outbound['breakdown'],
        ]);

        if ($request->roundTrip && $request->returnAt !== null) {
            $return = $this->priceLeg($hub, $vehicle, $route, $fixedRoute, $request->returnAt, 0);
            $discount = (float) $hub->return_discount_percent;
            $returnPrice = $this->roundUp($return['price'] * (1 - $discount / 100));
            $legs[] = ['leg' => 'return', ...$return, 'return_discount_percent' => $discount, 'price' => $returnPrice];
            $lines[] = $this->line('transfer', "{$toLabel} → {$fromLabel} (return)", 1, $returnPrice, [
                'leg' => 'return',
                'pickup_at' => $request->returnAt->format('Y-m-d H:i'),
                'return_discount_percent' => $discount,
                'breakdown' => $return['breakdown'],
            ]);
        }

        foreach ($request->extras as $code) {
            $extra = $hub->extra($code);

            if ($extra === null) {
                $this->addError('extras', "The extra “{$code}” is not available.");

                continue;
            }

            $lines[] = $this->line('extra', $extra['label'], 1, $extra['price'], ['code' => $code], commissionable: false);
        }

        return $this->buildResult(
            serviceType: ServiceType::Transfer,
            lines: $lines,
            seasonName: $fixedRoute !== null ? $this->prices->resolve($fixedRoute, $this->nationalityGroup?->id, $request->pickupAt)->seasonName() : null,
            depositPercent: $hub->deposit_percent !== null ? (float) $hub->deposit_percent : 100.0,
            currency: $request->currency,
            context: [
                ...$this->baseContext($request),
                'hub' => ['id' => $hub->id, 'name' => $hubName, 'slug' => $hub->slug],
                'vehicle' => ['id' => $vehicle->id, 'name' => (string) $vehicle->name, 'category' => $vehicle->category->value, 'capacity' => $vehicle->capacity, 'luggage' => $vehicle->luggage],
                'distance_km' => $route->distanceKm,
                'duration_minutes' => $route->durationMinutes,
                'distance_provider' => $route->provider,
                'distance_estimated' => $route->estimated,
                'fixed_route_id' => $fixedRoute?->id,
                'legs' => $legs,
                'valid_until' => now()->addMinutes((int) config('transfers.quote_validity_minutes', 60))->toIso8601String(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function baseContext(TransferQuoteRequestData $request): array
    {
        return [
            'pricing_mode' => 'per_vehicle',
            'direction' => $request->direction->value,
            'destination' => ['name' => $request->destinationName, 'lat' => $request->destinationLat, 'lng' => $request->destinationLng],
            'pickup_at' => $request->pickupAt->format('Y-m-d H:i'),
            'round_trip' => $request->roundTrip,
            'return_at' => $request->returnAt?->format('Y-m-d H:i'),
            'passengers' => $request->passengers,
            'luggage' => $request->luggage,
            'extras' => $request->extras,
            'adults' => $request->passengers,
            'children' => 0,
        ];
    }

    private function validateRequest(TransferHub $hub, VehicleType $vehicle, TransferQuoteRequestData $request): void
    {
        if ($request->passengers > $vehicle->capacity) {
            $this->addError('passengers', "The {$vehicle->name} carries at most {$vehicle->capacity} passengers.");
        }

        if ($request->luggage > $vehicle->luggage) {
            $this->addError('luggage', "The {$vehicle->name} carries at most {$vehicle->luggage} pieces of luggage.");
        }

        if ($request->pickupAt->lt(CarbonImmutable::now()->addHours($hub->min_notice_hours))) {
            $this->addError('pickup_at', "Book at least {$hub->min_notice_hours} hours before the pickup time.");
        }

        if ($request->roundTrip && ($request->returnAt === null || $request->returnAt->lte($request->pickupAt))) {
            $this->addError('return_at', 'The return must be after the outbound pickup.');
        }

        $straight = HaversineDistanceProvider::straightLineKm($hub->lat, $hub->lng, $request->destinationLat, $request->destinationLng);

        if ($straight > $hub->radius_km) {
            $this->addError('destination', "This destination is outside the service area of {$hub->name} ({$hub->radius_km} km).");
        }
    }

    /**
     * Road distance, cached for repeated hub/destination pairs.
     *
     * The cache holds a plain array: the cache store refuses to unserialize objects
     * (config cache.serializable_classes = false), so a cached RouteData would come back
     * as __PHP_Incomplete_Class on the second quote for the same pair.
     */
    private function route(TransferHub $hub, TransferQuoteRequestData $request): RouteData
    {
        $key = sprintf('transfer-route:v2:%.4f,%.4f:%.4f,%.4f', $hub->lat, $hub->lng, $request->destinationLat, $request->destinationLng);

        $cached = Cache::remember($key, now()->addDay(), function () use ($hub, $request): array {
            $route = $this->distances->route($hub->lat, $hub->lng, $request->destinationLat, $request->destinationLng);

            return [
                'distance_km' => $route->distanceKm,
                'duration_minutes' => $route->durationMinutes,
                'provider' => $route->provider,
                'estimated' => $route->estimated,
            ];
        });

        return new RouteData(
            (float) $cached['distance_km'],
            (int) $cached['duration_minutes'],
            (string) $cached['provider'],
            (bool) $cached['estimated'],
        );
    }

    private function matchingFixedRoute(TransferHub $hub, VehicleType $vehicle, TransferQuoteRequestData $request): ?TransferFixedRoute
    {
        return $this->fixedRoutes->forHubAndVehicle($hub->id, $vehicle->id)
            ->first(fn (TransferFixedRoute $route): bool => HaversineDistanceProvider::straightLineKm($route->lat, $route->lng, $request->destinationLat, $request->destinationLng) <= $route->match_radius_km);
    }

    /**
     * One leg: fixed route price, or the distance formula with tiers, waiting, night surcharge, multiplier, minimum and fees.
     *
     * @return array{price: float, breakdown: array<string, mixed>}
     */
    private function priceLeg(TransferHub $hub, VehicleType $vehicle, RouteData $route, ?TransferFixedRoute $fixedRoute, CarbonImmutable $pickupAt, int $waitingMinutes): array
    {
        $multiplier = (float) $vehicle->multiplier;

        if ($fixedRoute !== null) {
            $factor = $fixedRoute->vehicle_type_id === null ? $multiplier : 1.0;
            $resolved = $this->prices->resolve($fixedRoute, $this->nationalityGroup?->id, $pickupAt);
            $fixedPrice = $resolved->price('price') ?? (float) $fixedRoute->price;
            $price = $this->roundUp($fixedPrice * $factor);

            return [
                'price' => $price,
                'breakdown' => [
                    'method' => 'fixed_route',
                    'fixed_route_id' => $fixedRoute->id,
                    'fixed_price' => $fixedPrice,
                    'price_source' => $resolved->source('price')?->value,
                    'multiplier' => $factor,
                    'total' => $price,
                ],
            ];
        }

        $tiers = [];
        $distanceCharge = 0.0;
        $previousLimit = 0.0;

        foreach ($hub->distance_tiers ?? [] as $tier) {
            $limit = isset($tier['up_to_km']) && $tier['up_to_km'] !== null && $tier['up_to_km'] !== '' ? (float) $tier['up_to_km'] : PHP_FLOAT_MAX;
            $kilometres = max(0.0, min($route->distanceKm, $limit) - $previousLimit);

            if ($kilometres > 0) {
                $charge = round($kilometres * (float) $tier['rate'], 2);
                $distanceCharge += $charge;
                $tiers[] = ['km' => round($kilometres, 2), 'rate' => (float) $tier['rate'], 'amount' => $charge];
            }

            $previousLimit = $limit;

            if ($route->distanceKm <= $limit) {
                break;
            }
        }

        $base = (float) $hub->base_fare;
        $waiting = round(max(0, $waitingMinutes - $hub->waiting_grace_minutes) * (float) $hub->waiting_rate_per_hour / 60, 2);
        $surcharge = $this->isNight($pickupAt) ? (float) $hub->night_surcharge : 0.0;
        $subtotal = round(($base + $distanceCharge + $waiting + $surcharge) * $multiplier, 2);
        $minimum = (float) ($vehicle->min_fare ?? $hub->min_fare);
        $fees = (float) $hub->fixed_fees;
        $price = $this->roundUp(max($minimum, $subtotal) + $fees);

        return [
            'price' => $price,
            'breakdown' => [
                'method' => 'distance',
                'distance_km' => $route->distanceKm,
                'base_fare' => $base,
                'tiers' => $tiers,
                'distance_charge' => round($distanceCharge, 2),
                'waiting' => $waiting,
                'surcharge' => $surcharge,
                'multiplier' => $multiplier,
                'subtotal' => $subtotal,
                'minimum_fare' => $minimum,
                'minimum_applied' => $minimum > $subtotal,
                'fees' => $fees,
                'total' => $price,
            ],
        ];
    }

    private function isNight(CarbonImmutable $time): bool
    {
        $clock = $time->format('H:i');
        $from = (string) config('transfers.night_from', '22:00');
        $to = (string) config('transfers.night_to', '06:00');

        return $from > $to ? ($clock >= $from || $clock < $to) : ($clock >= $from && $clock < $to);
    }

    private function roundUp(float $amount): float
    {
        $step = (float) config('transfers.rounding_step', 1);

        return $step > 0 ? round(ceil(round($amount, 2) / $step) * $step, 2) : round($amount, 2);
    }
}
