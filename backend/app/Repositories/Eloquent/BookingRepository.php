<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Booking\BookingFilterData;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
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
}
