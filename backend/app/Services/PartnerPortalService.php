<?php

namespace App\Services;

use App\DTOs\Partner\PartnerCatalogueFilterData;
use App\DTOs\Pricing\CommissionData;
use App\Enums\BookingStatus;
use App\Enums\CatalogueService;
use App\Enums\PackagePriceMode;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Partner;
use App\Models\Room;
use App\Models\TransferFixedRoute;
use App\Models\Trip;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use App\Repositories\Contracts\PaymentProofRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Repositories\Contracts\TransferFixedRouteRepositoryInterface;
use App\Repositories\Contracts\TripRepositoryInterface;
use App\Services\Pricing\CommissionResolver;
use App\Services\Pricing\PriceResolver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Partner panel (SRS 9.3): KPIs, bookings, monthly statements, catalogue and price list at partner prices
 * (customer price for the client's nationality − commission %), commissions and profile.
 */
class PartnerPortalService
{
    public const STATEMENT_DUE_DAYS = 30;

    public function __construct(
        private readonly BookingRepositoryInterface $bookings,
        private readonly PartnerRepositoryInterface $partners,
        private readonly PaymentProofRepositoryInterface $proofs,
        private readonly TripRepositoryInterface $trips,
        private readonly PackageRepositoryInterface $packages,
        private readonly RoomRepositoryInterface $rooms,
        private readonly TransferFixedRouteRepositoryInterface $fixedRoutes,
        private readonly CommissionResolver $commissions,
        private readonly PriceResolver $prices,
        private readonly NationalityService $nationalities,
        private readonly CurrencyService $currencies,
        private readonly SeasonPriceService $seasons,
    ) {}

    /**
     * @return array{kpis: array<string, float|int>, commissions: list<array<string, mixed>>, recent_bookings: Collection<int, Booking>}
     */
    public function dashboard(Partner $partner): array
    {
        $monthStart = CarbonImmutable::now()->startOfMonth();
        $active = $this->bookings->partnerQuery($partner->id)->whereNotIn('status', BookingStatus::voided())->with('payments')->get();
        $month = $active->filter(fn (Booking $booking): bool => $booking->created_at->gte($monthStart));

        return [
            'kpis' => [
                'bookings_month' => $month->count(),
                'sales_month' => round((float) $month->sum(fn (Booking $booking): float => (float) $booking->retail_total), 2),
                'commission_saved_month' => round((float) $month->sum(fn (Booking $booking): float => (float) $booking->commission_amount), 2),
                'balance_due' => round((float) $active->sum(fn (Booking $booking): float => $booking->dueAmount()), 2),
                'pending_proofs' => $this->proofs->countPendingForPartner($partner->id),
                'commission_earned_month' => round((float) $month->sum(fn (Booking $booking): float => (float) $booking->commission_amount), 2),
                'partner_sales_month' => round((float) $month->sum(fn (Booking $booking): float => (float) ($booking->partner_total ?? $booking->total)), 2),
            ],
            'commissions' => $this->commissions->effectiveFor($partner),
            'recent_bookings' => $this->bookings->partnerQuery($partner->id)->with(['travelers', 'units', 'addons', 'rooms', 'bookable', 'nationalityGroup'])->limit(5)->get(),
        ];
    }

    /**
     * @param  array{service?: string|null, status?: string|null, payment_status?: string|null}  $filters
     */
    public function bookings(Partner $partner, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->bookings->partnerQuery($partner->id, $filters)
            ->with(['travelers', 'units', 'addons', 'rooms', 'bookable', 'payments.method', 'proofs', 'includedTransfers', 'transferRequest', 'nationalityGroup'])
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Monthly statements (by booking creation month), newest first, with their PDF and Excel export links.
     *
     * @return list<array<string, mixed>>
     */
    public function statements(Partner $partner): array
    {
        return $this->bookings->partnerQuery($partner->id)
            ->whereNotIn('status', BookingStatus::voided())
            ->with('payments')
            ->get()
            ->groupBy(fn (Booking $booking): string => $booking->created_at->format('Y-m'))
            ->map(fn ($group, string $period): array => $this->statementSummary($period, $group))
            ->sortByDesc('period')
            ->values()
            ->all();
    }

    /**
     * One month's statement with its bookings (for the PDF / Excel export). The month may be empty.
     *
     * @return array{summary: array<string, mixed>, bookings: list<array<string, mixed>>}
     */
    public function statement(Partner $partner, string $period): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d', $period.'-01')->startOfDay();

        $bookings = $this->bookings->partnerQuery($partner->id)
            ->reorder()
            ->whereNotIn('status', BookingStatus::voided())
            ->whereBetween('created_at', [$start, $start->endOfMonth()])
            ->with(['payments', 'bookable'])
            ->orderBy('created_at')
            ->get();

        return [
            'summary' => $this->statementSummary($period, $bookings),
            'bookings' => $bookings->map(fn (Booking $booking): array => [
                'reference' => $booking->reference,
                'created_at' => $booking->created_at->toDateString(),
                'service' => $booking->service_type->label(),
                'product' => $booking->bookable ? (string) ($booking->bookable->title ?? $booking->bookable->name ?? '') : '',
                'service_date' => ($booking->start_date ?? $booking->check_in)?->toDateString(),
                'lead_name' => $booking->lead_name,
                'retail_total' => (float) $booking->retail_total,
                'commission_amount' => (float) $booking->commission_amount,
                'partner_total' => (float) ($booking->partner_total ?? $booking->total),
                'paid' => $booking->paidAmount(),
                'balance' => round(max(0, (float) ($booking->partner_total ?? $booking->total) - $booking->paidAmount()), 2),
                'status' => $booking->status->label(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  SupportCollection<int, Booking>  $group
     * @return array<string, mixed>
     */
    private function statementSummary(string $period, SupportCollection $group): array
    {
        $today = CarbonImmutable::today();
        $start = CarbonImmutable::createFromFormat('Y-m-d', $period.'-01')->startOfDay();
        $end = $start->endOfMonth()->startOfDay();
        $total = round((float) $group->sum(fn (Booking $booking): float => (float) ($booking->partner_total ?? $booking->total)), 2);
        $paid = round((float) $group->sum(fn (Booking $booking): float => $booking->paidAmount()), 2);
        $balance = round(max(0, $total - $paid), 2);

        return [
            'id' => (int) $start->format('Ym'),
            'period' => $period,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'bookings_count' => $group->count(),
            'retail_total' => round((float) $group->sum(fn (Booking $booking): float => (float) $booking->retail_total), 2),
            'commission_amount' => round((float) $group->sum(fn (Booking $booking): float => (float) $booking->commission_amount), 2),
            'partner_total' => $total,
            'paid' => $paid,
            'balance' => $balance,
            'status' => match (true) {
                $balance <= 0 => 'paid',
                $end->addDays(self::STATEMENT_DUE_DAYS)->lt($today) => 'overdue',
                default => 'open',
            },
            'pdf_url' => route('api.v1.partner.statements.export', ['period' => $period, 'format' => 'pdf']),
            'excel_url' => route('api.v1.partner.statements.export', ['period' => $period, 'format' => 'xlsx']),
        ];
    }

    /**
     * New booking catalogue (CR-15): every bookable product with its customer price for the client's
     * nationality group (and date, when given), the partner price and the commission applied.
     *
     * @return list<array<string, mixed>>
     */
    public function catalogue(Partner $partner, PartnerCatalogueFilterData $filters): array
    {
        $group = $this->nationalities->groupFor($filters->nationalityCode);
        $tokens = $filters->searchTokens();
        $services = $filters->service !== null ? [$filters->service] : CatalogueService::cases();
        $items = [];

        foreach ($services as $service) {
            $commission = $this->commissions->resolve($partner, $service->commissionCategory());

            $products = match ($service) {
                CatalogueService::Packages => $this->packages->forCatalogue($filters->destination, $tokens),
                CatalogueService::Accommodation => $this->rooms->forCatalogue($filters->destination, $tokens),
                CatalogueService::Transfers => $this->fixedRoutes->forCatalogue(),
                default => $this->trips->forCatalogue([$service->tripType()->value], $filters->destination, $tokens),
            };

            foreach ($products as $product) {
                $item = $this->catalogueItem($service, $product, $group->id, $commission, $filters);

                if ($item !== null) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * Read-only price list for a chosen nationality: per product one "base price" row (outside every season)
     * and one row per active current/upcoming season that applies to the group (a group's own season hides an
     * all-groups season it overrides), each with customer and partner price.
     *
     * @return list<array<string, mixed>>
     */
    public function priceList(Partner $partner, PartnerCatalogueFilterData $filters): array
    {
        $group = $this->nationalities->groupFor($filters->nationalityCode);
        $groupSummary = $group->summary();
        $tokens = $filters->searchTokens();
        $services = $filters->service !== null ? [$filters->service] : CatalogueService::cases();
        $today = CarbonImmutable::today();
        $rows = [];

        foreach ($services as $service) {
            $commission = $this->commissions->resolve($partner, $service->commissionCategory());

            $products = match ($service) {
                CatalogueService::Packages => $this->packages->forCatalogue($filters->destination, $tokens),
                CatalogueService::Accommodation => $this->rooms->forCatalogue($filters->destination, $tokens),
                CatalogueService::Transfers => $this->fixedRoutes->forCatalogue(),
                default => $this->trips->forCatalogue([$service->tripType()->value], $filters->destination, $tokens),
            };

            foreach ($products as $product) {
                if ($product instanceof TransferFixedRoute && ! $this->matchesRouteSearch($product, $filters)) {
                    continue;
                }

                [, , $title, , , $unit] = $this->describe($product);
                $row = fn (string $type, ?string $season, ?CarbonImmutable $from, ?CarbonImmutable $to, ?float $customer): array => [
                    'service' => $service->value,
                    'service_type' => $this->serviceType($service)->value,
                    'title' => $title,
                    'slug' => $product instanceof Trip || $product instanceof Package ? $product->slug : null,
                    'row_type' => $type,
                    'season' => $season,
                    'starts_on' => $from?->toDateString(),
                    'ends_on' => $to?->toDateString(),
                    'pricing_mode' => $unit,
                    'retail_price' => $this->currencies->money($customer),
                    'partner_price' => $this->currencies->money($customer !== null ? $commission->apply($customer) : null),
                    'commission' => ['percent' => $commission->percent, 'source' => $commission->source->value],
                    'nationality_group' => $groupSummary,
                ];

                // Computed packages are priced from their stages: one "from" row.
                if ($product instanceof Package && $product->price_mode === PackagePriceMode::Computed) {
                    $rows[] = $row('from', null, null, null, $this->prices->lowestPrice($product, $group->id));

                    continue;
                }

                $rows[] = $row('base', null, null, null, $this->prices->lowestOf($this->prices->base($product, $group->id)));

                foreach ($this->seasons->priceList($product, $today, $group->id) as $entry) {
                    $date = $entry['starts_on']->lt($today) ? $today : $entry['starts_on'];
                    $resolved = $this->prices->resolve($product, $group->id, $date);

                    if ($resolved->season()?->id !== $entry['season']->id) {
                        continue; // overridden by the group's own season on these dates
                    }

                    $rows[] = $row('season', (string) $entry['season']->name, $entry['starts_on'], $entry['ends_on'], $this->prices->lowestOf($resolved));
                }
            }
        }

        return $rows;
    }

    /**
     * Commission percentage per type with its source (read-only in the partner profile).
     *
     * @return list<array<string, mixed>>
     */
    public function commissions(Partner $partner): array
    {
        return $this->commissions->effectiveFor($partner);
    }

    /**
     * @param  array<string, mixed>  $attributes  contact_name, phone, website, country, tax_number, notify_bookings, notify_statements
     */
    public function updateProfile(Partner $partner, array $attributes): Partner
    {
        return $this->partners->update($partner, $attributes);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function catalogueItem(CatalogueService $service, Model $product, int $groupId, CommissionData $commission, PartnerCatalogueFilterData $filters): ?array
    {
        if ($product instanceof TransferFixedRoute && ! $this->matchesRouteSearch($product, $filters)) {
            return null;
        }

        // Without a date: the lowest price of the base and the active current/upcoming seasons ("From").
        // With a date: the price of that date's season for the group, labelled with the season name.
        if ($filters->date === null) {
            $customer = $this->prices->lowestPrice($product, $groupId);
            $season = null;
        } else {
            $customer = $this->prices->lowestPrice($product, $groupId, $filters->date);
            $season = $this->prices->resolve($product, $groupId, $filters->date)->seasonName();
        }

        [$id, $slug, $title, $subtitle, $image, $unit] = $this->describe($product);

        return [
            'service' => $service->value,
            'service_type' => $this->serviceType($service)->value,
            'product_type' => $service->productType(),
            'id' => $id,
            'slug' => $slug,
            'title' => $title,
            'subtitle' => $subtitle !== null ? (string) $subtitle : null,
            'cover_image' => $image,
            'price_unit' => $unit,
            'price_basis' => $filters->date === null ? 'from' : 'date',
            'season' => $season,
            'customer_price' => $this->currencies->money($customer),
            'partner_price' => $this->currencies->money($customer !== null ? $commission->apply($customer) : null),
            'commission' => ['percent' => $commission->percent, 'source' => $commission->source->value],
            'nationality_group' => $this->nationalities->groups()->firstWhere('id', $groupId)?->summary(),
            'hub_id' => $product instanceof TransferFixedRoute ? $product->transfer_hub_id : null,
            'vehicle_type_id' => $product instanceof TransferFixedRoute ? $product->vehicle_type_id : null,
            'location' => $product instanceof TransferFixedRoute ? ['lat' => $product->lat, 'lng' => $product->lng] : null,
        ];
    }

    private function matchesRouteSearch(TransferFixedRoute $route, PartnerCatalogueFilterData $filters): bool
    {
        if ($filters->search === null) {
            return true;
        }

        $haystack = mb_strtolower($route->destination_name.' '.$route->hub?->name);

        foreach ($filters->searchTokens() as $token) {
            if (! str_contains($haystack, $token)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{0: int, 1: string|null, 2: string, 3: mixed, 4: string|null, 5: string}
     */
    private function describe(Model $product): array
    {
        return match (true) {
            $product instanceof Trip => [$product->id, $product->slug, (string) $product->title, $product->destination?->name, $product->cover_image, $product->pricing_mode->value],
            $product instanceof Package => [$product->id, $product->slug, (string) $product->title, $product->destination?->name, $product->cover_image, 'per_person'],
            $product instanceof Room => [$product->id, null, $product->building->name.' · '.$product->name, $product->building->destination?->name, $product->cover_image, 'per_night'],
            $product instanceof TransferFixedRoute => [$product->id, null, $product->hub?->name.' → '.$product->destination_name, $product->vehicleType?->name ?? 'Any vehicle', null, 'per_vehicle'],
        };
    }

    private function serviceType(CatalogueService $service): ServiceType
    {
        return match ($service) {
            CatalogueService::NileTrips => ServiceType::NileTrip,
            CatalogueService::Packages => ServiceType::Package,
            CatalogueService::Transfers => ServiceType::Transfer,
            CatalogueService::Accommodation => ServiceType::Accommodation,
            default => ServiceType::Trip,
        };
    }
}
