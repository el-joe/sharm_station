<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Report\ReportFilterData;
use App\Enums\BookingStatus;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Repositories\Contracts\ReportRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ReportRepository implements ReportRepositoryInterface
{
    public function kpis(ReportFilterData $filters): array
    {
        $totals = $this->bookings($filters)
            ->selectRaw('COUNT(*) as bookings, COALESCE(SUM(b.total), 0) as revenue, COALESCE(SUM(b.discount_amount), 0) as discounts')
            ->selectRaw('COALESCE(SUM(CASE WHEN b.partner_id IS NOT NULL THEN b.total ELSE 0 END), 0) as partner_sales')
            ->selectRaw('COALESCE(SUM(CASE WHEN b.partner_id IS NOT NULL THEN b.commission_amount ELSE 0 END), 0) as commission_granted')
            ->first();

        $paid = $this->bookings($filters)
            ->leftJoinSub($this->netPayments(), 'p', 'p.booking_id', '=', 'b.id')
            ->selectRaw('COALESCE(SUM(GREATEST(b.total - COALESCE(p.net, CASE b.payment_status WHEN ? THEN b.total WHEN ? THEN b.deposit_amount ELSE 0 END), 0)), 0) as balance_due', [PaymentStatus::FullyPaid->value, PaymentStatus::DepositPaid->value])
            ->first();

        $deposits = DB::table('payments as pay')
            ->join('bookings as b', 'b.id', '=', 'pay.booking_id')
            ->where('pay.status', PaymentRecordStatus::Succeeded->value)
            ->where('pay.type', PaymentType::Deposit->value)
            ->whereBetween(DB::raw('COALESCE(pay.paid_at, pay.created_at)'), [$filters->from, $filters->to])
            ->when($filters->serviceType, fn (Builder $query, $service) => $query->where('b.service_type', $service->value))
            ->sum('pay.amount');

        return [
            'bookings' => (int) $totals->bookings,
            'revenue' => round((float) $totals->revenue, 2),
            'deposits_collected' => round((float) $deposits, 2),
            'balance_due' => round((float) $paid->balance_due, 2),
            'partner_sales' => round((float) $totals->partner_sales, 2),
            'commission_granted' => round((float) $totals->commission_granted, 2),
            'discounts' => round((float) $totals->discounts, 2),
        ];
    }

    public function revenueByServiceAndMonth(ReportFilterData $filters): array
    {
        return $this->bookings($filters)
            ->selectRaw("DATE_FORMAT(b.created_at, '%Y-%m') as period, b.service_type, COUNT(*) as bookings, COALESCE(SUM(b.total), 0) as revenue")
            ->groupBy('period', 'b.service_type')
            ->orderBy('period')
            ->orderBy('b.service_type')
            ->get()
            ->map(fn (object $row): array => ['period' => $row->period, 'service_type' => $row->service_type, 'bookings' => (int) $row->bookings, 'revenue' => round((float) $row->revenue, 2)])
            ->all();
    }

    public function bestSellers(ReportFilterData $filters, int $limit = 15): array
    {
        return $this->bookings($filters)
            ->selectRaw('b.bookable_type, b.bookable_id, MIN(b.service_type) as service_type, COUNT(*) as bookings, COALESCE(SUM(b.adults + b.children), 0) as persons, COALESCE(SUM(b.total), 0) as revenue')
            ->groupBy('b.bookable_type', 'b.bookable_id')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'bookable_type' => $row->bookable_type,
                'bookable_id' => $row->bookable_id !== null ? (int) $row->bookable_id : null,
                'service_type' => $row->service_type,
                'bookings' => (int) $row->bookings,
                'persons' => (int) $row->persons,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    public function partnerSales(ReportFilterData $filters): array
    {
        return $this->bookings($filters)
            ->join('partners as pa', 'pa.id', '=', 'b.partner_id')
            ->selectRaw('pa.id as partner_id, pa.company, COUNT(*) as bookings, COALESCE(SUM(b.retail_total), 0) as retail_total, COALESCE(SUM(b.total), 0) as partner_total, COALESCE(SUM(b.commission_amount), 0) as commission')
            ->groupBy('pa.id', 'pa.company')
            ->orderByDesc('partner_total')
            ->get()
            ->map(fn (object $row): array => [
                'partner_id' => (int) $row->partner_id,
                'company' => (string) $row->company,
                'bookings' => (int) $row->bookings,
                'retail_total' => round((float) $row->retail_total, 2),
                'partner_total' => round((float) $row->partner_total, 2),
                'commission' => round((float) $row->commission, 2),
            ])
            ->all();
    }

    public function nationalityMix(ReportFilterData $filters): array
    {
        return $this->bookings($filters)
            ->leftJoin('nationality_groups as ng', 'ng.id', '=', 'b.nationality_group_id')
            ->selectRaw("b.nationality_group_id, JSON_UNQUOTE(JSON_EXTRACT(ng.name, '$.en')) as name, COUNT(*) as bookings, COALESCE(SUM(b.total), 0) as revenue")
            ->groupBy('b.nationality_group_id', 'name')
            ->orderByDesc('bookings')
            ->get()
            ->map(fn (object $row): array => [
                'nationality_group_id' => $row->nationality_group_id !== null ? (int) $row->nationality_group_id : null,
                'name' => $row->name,
                'bookings' => (int) $row->bookings,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    public function paymentsByMethod(ReportFilterData $filters): array
    {
        return DB::table('payments as pay')
            ->join('bookings as b', 'b.id', '=', 'pay.booking_id')
            ->leftJoin('payment_methods as pm', 'pm.id', '=', 'pay.payment_method_id')
            ->where('pay.status', PaymentRecordStatus::Succeeded->value)
            ->whereBetween(DB::raw('COALESCE(pay.paid_at, pay.created_at)'), [$filters->from, $filters->to])
            ->when($filters->serviceType, fn (Builder $query, $service) => $query->where('b.service_type', $service->value))
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(pm.name, '$.en')) as method, pay.type, COUNT(*) as payments, COALESCE(SUM(pay.amount), 0) as amount")
            ->groupBy('method', 'pay.type')
            ->orderByDesc('amount')
            ->get()
            ->map(fn (object $row): array => ['method' => $row->method, 'type' => $row->type, 'payments' => (int) $row->payments, 'amount' => round((float) $row->amount, 2)])
            ->all();
    }

    /**
     * Bookings created in the range (cancelled and refunded excluded), optionally for one service.
     */
    private function bookings(ReportFilterData $filters): Builder
    {
        return DB::table('bookings as b')
            ->whereNotIn('b.status', array_map(fn (BookingStatus $status): string => $status->value, BookingStatus::voided()))
            ->whereBetween('b.created_at', [$filters->from, $filters->to])
            ->when($filters->serviceType, fn (Builder $query, $service) => $query->where('b.service_type', $service->value));
    }

    /**
     * Net amount received per booking (succeeded payments, refunds subtracted).
     */
    private function netPayments(): Builder
    {
        return DB::table('payments')
            ->where('status', PaymentRecordStatus::Succeeded->value)
            ->selectRaw('booking_id, SUM(CASE WHEN type = ? THEN -amount ELSE amount END) as net', [PaymentType::Refund->value])
            ->groupBy('booking_id');
    }
}
