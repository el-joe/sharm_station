<?php

namespace App\Services;

use App\Enums\PartnerStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Admin overview (SRS 14.1 Statistics): KPIs, 30-day chart, tasks that need attention, recent bookings.
 */
class DashboardService
{
    public const CHART_DAYS = 30;

    public const HOLD_WARNING_MINUTES = 120;

    public function __construct(
        private readonly BookingRepositoryInterface $bookings,
        private readonly PartnerRepositoryInterface $partners,
        private readonly RoomRepositoryInterface $rooms,
    ) {}

    /**
     * @return array{bookings_today: int, revenue_month: float, balance_due: float, partner_sales_month: float, revenue_last_month: float}
     */
    public function kpis(?CarbonInterface $now = null): array
    {
        $now = CarbonImmutable::parse($now ?? now());
        $monthStart = $now->startOfMonth();
        $lastMonthStart = $monthStart->subMonth();

        return [
            'bookings_today' => $this->bookings->countCreatedBetween($now->startOfDay(), $now->endOfDay()),
            'revenue_month' => $this->bookings->sumCreatedBetween('total', $monthStart, $now->endOfDay()),
            'revenue_last_month' => $this->bookings->sumCreatedBetween('total', $lastMonthStart, $monthStart->subSecond()),
            'balance_due' => $this->bookings->balanceDue(),
            'partner_sales_month' => $this->bookings->sumCreatedBetween('total', $monthStart, $now->endOfDay(), partnerOnly: true),
        ];
    }

    /**
     * Daily bookings and deposits collected for the last 30 days (Chart.js payload).
     *
     * @return array{labels: list<string>, bookings: list<int>, deposits: list<float>}
     */
    public function chart(?CarbonInterface $now = null, int $days = self::CHART_DAYS): array
    {
        $now = CarbonImmutable::parse($now ?? now());
        $from = $now->subDays($days - 1)->startOfDay();
        $totals = $this->bookings->dailyTotals($from, $now->endOfDay());
        $chart = ['labels' => [], 'bookings' => [], 'deposits' => []];

        for ($day = $from; $day->lte($now); $day = $day->addDay()) {
            $key = $day->toDateString();
            $chart['labels'][] = $day->format('j M');
            $chart['bookings'][] = $totals[$key]['bookings'] ?? 0;
            $chart['deposits'][] = $totals[$key]['deposits'] ?? 0.0;
        }

        return $chart;
    }

    /**
     * Operational tasks, each with a count, detail line and a target URL.
     *
     * @return list<array{icon: string, tone: string, title: string, detail: string, action: string, url: string}>
     */
    public function needsAttention(): array
    {
        $tasks = [];

        $receipts = $this->bookings->countByPaymentStatus(PaymentStatus::AwaitingReceiptReview);
        if ($receipts > 0) {
            $oldest = $this->bookings->oldestWithPaymentStatus(PaymentStatus::AwaitingReceiptReview);
            $tasks[] = [
                'icon' => 'receipt', 'tone' => 'warning',
                'title' => trans_choice(':count receipt waiting for review|:count receipts waiting for review', $receipts),
                'detail' => $oldest ? "Oldest: {$oldest->reference}, ".$oldest->created_at->diffForHumans() : '',
                'action' => 'Review', 'url' => route('admin.bookings.index', ['payment_status' => PaymentStatus::AwaitingReceiptReview->value]),
            ];
        }

        $awaiting = $this->bookings->countByPaymentStatus(PaymentStatus::AwaitingPayment);
        if ($awaiting > 0) {
            $tasks[] = [
                'icon' => 'wallet', 'tone' => 'error',
                'title' => trans_choice(':count booking awaiting payment|:count bookings awaiting payment', $awaiting),
                'detail' => 'Deposit not received yet',
                'action' => 'Open', 'url' => route('admin.bookings.index', ['payment_status' => PaymentStatus::AwaitingPayment->value]),
            ];
        }

        $applications = $this->partners->countByStatus(PartnerStatus::Pending);
        if ($applications > 0) {
            $tasks[] = [
                'icon' => 'handshake', 'tone' => 'primary',
                'title' => trans_choice(':count partner application|:count partner applications', $applications),
                'detail' => 'Approve or reject',
                'action' => 'Review', 'url' => route('admin.partners.index', ['status' => PartnerStatus::Pending->value]),
            ];
        }

        $holds = $this->rooms->holdsExpiringWithin(self::HOLD_WARNING_MINUTES);
        if ($holds->isNotEmpty()) {
            $first = $holds->first();
            $tasks[] = [
                'icon' => 'clock', 'tone' => 'warning',
                'title' => trans_choice(':count room hold expires in 2 hours|:count room holds expire in 2 hours', $holds->count()),
                'detail' => trim(($first->room?->building?->name ?? '').', '.($first->room?->name ?? ''), ', '),
                'action' => 'Open', 'url' => $first->room ? route('admin.rooms.calendar', $first->room) : route('admin.rooms.index'),
            ];
        }

        return $tasks;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function recentBookings(int $limit = 5): Collection
    {
        return $this->bookings->recent($limit);
    }
}
