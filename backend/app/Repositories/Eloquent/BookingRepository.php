<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Booking\BookingFilterData;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Repositories\Contracts\BookingRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Booking>
 */
class BookingRepository extends BaseRepository implements BookingRepositoryInterface
{
    protected function model(): string
    {
        return Booking::class;
    }

    public function findByReference(string $reference): ?Booking
    {
        return $this->query()
            ->where('reference', strtoupper($reference))
            ->with(['travelers', 'units', 'addons', 'rooms.guests', 'bookable'])
            ->first();
    }

    public function paginateForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->where('user_id', $userId)
            ->with(['travelers', 'units', 'addons', 'rooms', 'bookable', 'payments.method', 'proofs', 'includedTransfers', 'transferRequest'])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function partnerQuery(int $partnerId, array $filters = []): Builder
    {
        return $this->query()
            ->where('partner_id', $partnerId)
            ->when($filters['service'] ?? null, fn (Builder $query, string $service) => $query->where('service_type', $service))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status) => $query->where('payment_status', $status))
            ->latest('id');
    }

    public function lastReferenceNumber(): ?int
    {
        $max = $this->query()
            ->where('reference', 'like', 'BK-%')
            ->lockForUpdate()
            ->selectRaw('MAX(CAST(SUBSTRING(reference, 4) AS UNSIGNED)) as max_number')
            ->value('max_number');

        return $max !== null ? (int) $max : null;
    }

    public function adminQuery(BookingFilterData $filters): Builder
    {
        return $this->query()
            ->select('bookings.*')
            ->with(['partner:id,company'])
            ->when($filters->search, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                $query->where('reference', 'like', "%{$search}%")
                    ->orWhere('lead_name', 'like', "%{$search}%")
                    ->orWhere('lead_email', 'like', "%{$search}%")
                    ->orWhereRaw("REPLACE(lead_phone, ' ', '') like ?", ['%'.preg_replace('/\s+/', '', $search).'%']);
            }))
            ->when($filters->serviceType, fn (Builder $query, $type) => $query->where('service_type', $type))
            ->when($filters->bookingType, fn (Builder $query, $type) => $query->where('booking_type', $type))
            ->when($filters->status, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters->paymentStatus, fn (Builder $query, $status) => $query->where('payment_status', $status))
            ->when($filters->partnerId, fn (Builder $query, int $partnerId) => $query->where('partner_id', $partnerId))
            ->when($filters->dateFrom, fn (Builder $query, CarbonInterface $from) => $query->whereRaw('COALESCE(start_date, check_in) >= ?', [$from->toDateString()]))
            ->when($filters->dateTo, fn (Builder $query, CarbonInterface $to) => $query->whereRaw('COALESCE(start_date, check_in) <= ?', [$to->toDateString()]))
            ->when($filters->cancellation === 'any', fn (Builder $query) => $query->whereNotNull('cancellation_requested_at'))
            ->when($filters->cancellation === 'pending', fn (Builder $query) => $query->whereNotNull('cancellation_requested_at')->whereNull('cancellation_decision'));
    }

    public function countCreatedBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        return $this->query()->whereBetween('created_at', [$from, $to])->count();
    }

    public function sumCreatedBetween(string $column, ?CarbonInterface $from = null, ?CarbonInterface $to = null, bool $partnerOnly = false): float
    {
        return (float) $this->query()
            ->whereNotIn('status', BookingStatus::voided())
            ->when($from && $to, fn (Builder $query) => $query->whereBetween('created_at', [$from, $to]))
            ->when($partnerOnly, fn (Builder $query) => $query->whereNotNull('partner_id'))
            ->sum($column);
    }

    public function balanceDue(): float
    {
        return (float) $this->query()
            ->whereNotIn('status', BookingStatus::voided())
            ->where('payment_status', PaymentStatus::DepositPaid)
            ->sum('balance_amount');
    }

    public function countByPaymentStatus(PaymentStatus $status): int
    {
        return $this->query()
            ->whereNotIn('status', BookingStatus::voided())
            ->where('payment_status', $status)
            ->count();
    }

    public function dailyTotals(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', BookingStatus::voided())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as bookings')
            ->selectRaw('SUM(CASE WHEN payment_status = ? THEN total WHEN payment_status = ? THEN deposit_amount ELSE 0 END) as deposits', [
                PaymentStatus::FullyPaid->value,
                PaymentStatus::DepositPaid->value,
            ])
            ->groupBy('day')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(string) $row->getAttribute('day')] = [
                'bookings' => (int) $row->getAttribute('bookings'),
                'deposits' => round((float) $row->getAttribute('deposits'), 2),
            ];
        }

        return $totals;
    }

    public function recent(int $limit = 5): Collection
    {
        return $this->query()->with('partner:id,company')->latest('id')->limit($limit)->get();
    }

    public function oldestWithPaymentStatus(PaymentStatus $status): ?Booking
    {
        return $this->query()
            ->whereNotIn('status', BookingStatus::voided())
            ->where('payment_status', $status)
            ->oldest('created_at')
            ->first();
    }

    public function withOpenBalance(CarbonInterface $today, int $pastDays = 60): Collection
    {
        $since = $today->copy()->subDays($pastDays)->toDateString();

        return $this->query()
            ->where('status', BookingStatus::Confirmed)
            ->where('payment_status', PaymentStatus::DepositPaid)
            ->where(fn (Builder $query) => $query->where('start_date', '>=', $since)->orWhere('check_in', '>=', $since))
            ->with(['payments', 'partner.user', 'bookable'])
            ->orderBy('id')
            ->get();
    }

    public function unpaidPastDeadline(CarbonInterface $now, ?bool $accommodation = null): Collection
    {
        return $this->query()
            ->where('status', BookingStatus::AwaitingPayment)
            ->whereIn('payment_status', [PaymentStatus::AwaitingPayment, PaymentStatus::Failed, PaymentStatus::ProofRejected])
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '<', $now)
            ->when($accommodation !== null, fn (Builder $query) => $accommodation
                ? $query->where('service_type', ServiceType::Accommodation)
                : $query->where('service_type', '!=', ServiceType::Accommodation))
            ->orderBy('id')
            ->get();
    }

    public function holdsEndingBetween(CarbonInterface $now, CarbonInterface $until): Collection
    {
        return $this->query()
            ->where('service_type', ServiceType::Accommodation)
            ->where('status', BookingStatus::AwaitingPayment)
            ->whereIn('payment_status', [PaymentStatus::AwaitingPayment, PaymentStatus::Failed, PaymentStatus::ProofRejected])
            ->whereBetween('payment_due_at', [$now, $until])
            ->orderBy('id')
            ->get();
    }

    public function endedBefore(CarbonInterface $day): Collection
    {
        return $this->query()
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::FullyPaid])
            ->where(fn (Builder $query) => $query
                ->where('end_date', '<', $day->toDateString())
                ->orWhere(fn (Builder $legacy) => $legacy->whereNull('end_date')->whereRaw('COALESCE(check_out, start_date) < ?', [$day->toDateString()])))
            ->orderBy('id')
            ->get();
    }

    public function servicesEndedBetween(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $from = $from->toDateString();
        $to = $to->toDateString();

        return $this->query()
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::FullyPaid, BookingStatus::Completed])
            ->where(fn (Builder $query) => $query
                ->whereBetween('check_out', [$from, $to])
                ->orWhere(fn (Builder $inner) => $inner->whereNull('check_out')->whereBetween('start_date', [$from, $to])))
            ->with('bookable')
            ->orderBy('id')
            ->get();
    }

    public function partnerTotals(int $partnerId, ?CarbonInterface $from = null, ?CarbonInterface $to = null, bool $byMonth = false): array
    {
        $paid = "COALESCE((SELECT SUM(CASE WHEN p.type = 'refund' THEN -p.amount ELSE p.amount END) FROM payments p WHERE p.booking_id = bookings.id AND p.status = 'succeeded'),"
            ." CASE bookings.payment_status WHEN 'fully_paid' THEN bookings.total WHEN 'deposit_paid' THEN bookings.deposit_amount ELSE 0 END)";

        $rows = $this->query()
            ->toBase()
            ->where('partner_id', $partnerId)
            ->whereNotIn('status', array_map(fn (BookingStatus $status): string => $status->value, BookingStatus::voided()))
            ->when($from, fn ($query, CarbonInterface $from) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query, CarbonInterface $to) => $query->where('created_at', '<=', $to))
            ->when($byMonth, fn ($query) => $query->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period")->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")->orderByDesc('period'))
            ->selectRaw('COUNT(*) as bookings_count')
            ->selectRaw('COALESCE(SUM(retail_total), 0) as retail_total')
            ->selectRaw('COALESCE(SUM(commission_amount), 0) as commission_amount')
            ->selectRaw('COALESCE(SUM(COALESCE(partner_total, total)), 0) as partner_total')
            ->selectRaw('COALESCE(SUM(total), 0) as total')
            ->selectRaw("COALESCE(SUM({$paid}), 0) as paid")
            ->get();

        return $rows->map(fn (object $row): array => [
            'period' => $row->period ?? null,
            'bookings_count' => (int) $row->bookings_count,
            'retail_total' => round((float) $row->retail_total, 2),
            'commission_amount' => round((float) $row->commission_amount, 2),
            'partner_total' => round((float) $row->partner_total, 2),
            'total' => round((float) $row->total, 2),
            'paid' => round((float) $row->paid, 2),
        ])->values()->all();
    }
}
