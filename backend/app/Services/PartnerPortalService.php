<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\CommissionCategory;
use App\Enums\ProofStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Partner;
use App\Models\Room;
use App\Models\Trip;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use App\Repositories\Contracts\PaymentProofRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Repositories\Contracts\TripRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Partner panel (SRS 9.3): KPIs, bookings, monthly statements, partner price list and profile.
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
        private readonly PartnerPricingService $partnerPricing,
        private readonly SeasonPriceService $seasons,
        private readonly CurrencyService $currencies,
    ) {}

    /**
     * @return array{kpis: array<string, float|int>, recent_bookings: Collection<int, Booking>}
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
                'pending_proofs' => $this->proofs->query()->where('status', ProofStatus::Pending)
                    ->whereHas('booking', fn ($booking) => $booking->where('partner_id', $partner->id))->count(),
            ],
            'recent_bookings' => $this->bookings->partnerQuery($partner->id)->with(['travelers', 'units', 'addons', 'rooms', 'bookable'])->limit(5)->get(),
        ];
    }

    /**
     * @param  array{service?: string|null, status?: string|null, payment_status?: string|null}  $filters
     */
    public function bookings(Partner $partner, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->bookings->partnerQuery($partner->id, $filters)
            ->with(['travelers', 'units', 'addons', 'rooms', 'bookable', 'payments.method', 'proofs', 'includedTransfers', 'transferRequest'])
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Monthly statements computed from the partner's bookings (by creation month, voided bookings excluded).
     *
     * @return list<array<string, mixed>>
     */
    public function statements(Partner $partner): array
    {
        $today = CarbonImmutable::today();

        return $this->bookings->partnerQuery($partner->id)
            ->whereNotIn('status', BookingStatus::voided())
            ->with('payments')
            ->get()
            ->groupBy(fn (Booking $booking): string => $booking->created_at->format('Y-m'))
            ->map(function ($group, string $period) use ($today): array {
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
                    'partner_total' => $total,
                    'paid' => $paid,
                    'balance' => $balance,
                    'status' => match (true) {
                        $balance <= 0 => 'paid',
                        $end->addDays(self::STATEMENT_DUE_DAYS)->lt($today) => 'overdue',
                        default => 'open',
                    },
                    'pdf_url' => null,
                    'excel_url' => null,
                ];
            })
            ->sortByDesc('period')
            ->values()
            ->all();
    }

    /**
     * Retail and partner "from" prices of every published product, with the season currently running.
     *
     * @return list<array<string, mixed>>
     */
    public function priceList(Partner $partner): array
    {
        $today = CarbonImmutable::today();
        $rows = [];

        foreach ($this->trips->query()->published()->with('seasonPrices')->orderBy('type')->orderBy('sort')->get() as $trip) {
            /** @var Trip $trip */
            $rows[] = $this->priceRow($partner, $trip->type->serviceType(), $trip->type->commissionCategory(), $trip, (string) $trip->title, $trip->slug, $trip->pricing_mode->value, $trip->from_price, $trip->partner_price, $today);
        }

        foreach ($this->packages->query()->published()->with('seasonPrices')->orderBy('sort')->get() as $package) {
            /** @var Package $package */
            $rows[] = $this->priceRow($partner, ServiceType::Package, CommissionCategory::Packages, $package, (string) $package->title, $package->slug, 'per_person', $package->from_price ?? $package->base_price, $package->partner_price, $today);
        }

        foreach ($this->rooms->query()->bookable()->with(['seasonPrices', 'building'])->orderBy('building_id')->orderBy('sort')->get() as $room) {
            /** @var Room $room */
            $rows[] = $this->priceRow($partner, ServiceType::Accommodation, CommissionCategory::Accommodation, $room, $room->building->name.' · '.$room->name, null, 'per_night', $room->from_price ?? $room->base_price, $room->partner_price, $today);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $attributes  contact_name, phone, website, country, tax_number, notify_bookings, notify_statements
     */
    public function updateProfile(Partner $partner, array $attributes): Partner
    {
        return $this->partners->update($partner, $attributes);
    }

    /**
     * @return array<string, mixed>
     */
    private function priceRow(Partner $partner, ServiceType $service, CommissionCategory $category, Model $product, string $title, ?string $slug, string $pricingMode, float|string|null $retail, float|string|null $explicit, CarbonImmutable $today): array
    {
        $season = $this->seasons->forDate($product, $today);
        $partnerPrice = $retail !== null ? $this->partnerPricing->unitPrice($partner, $category, (float) $retail, $explicit) : null;

        return [
            'service_type' => $service->value,
            'title' => $title,
            'slug' => $slug,
            'season' => $season?->name,
            'pricing_mode' => $pricingMode,
            'retail_price' => $this->currencies->money($retail),
            'partner_price' => $this->currencies->money($partnerPrice),
        ];
    }
}
