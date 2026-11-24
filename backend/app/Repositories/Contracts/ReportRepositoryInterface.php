<?php

namespace App\Repositories\Contracts;

use App\DTOs\Report\ReportFilterData;

/**
 * SQL aggregates for the reports (no booking is loaded in memory).
 */
interface ReportRepositoryInterface
{
    /**
     * @return array{bookings: int, revenue: float, deposits_collected: float, balance_due: float, partner_sales: float, commission_granted: float, discounts: float}
     */
    public function kpis(ReportFilterData $filters): array;

    /**
     * @return list<array{period: string, service_type: string, bookings: int, revenue: float}>
     */
    public function revenueByServiceAndMonth(ReportFilterData $filters): array;

    /**
     * @return list<array{bookable_type: string|null, bookable_id: int|null, service_type: string, bookings: int, persons: int, revenue: float}>
     */
    public function bestSellers(ReportFilterData $filters, int $limit = 15): array;

    /**
     * @return list<array{partner_id: int, company: string, bookings: int, retail_total: float, partner_total: float, commission: float}>
     */
    public function partnerSales(ReportFilterData $filters): array;

    /**
     * @return list<array{nationality_group_id: int|null, name: string|null, bookings: int, revenue: float}>
     */
    public function nationalityMix(ReportFilterData $filters): array;

    /**
     * @return list<array{method: string|null, type: string, payments: int, amount: float}>
     */
    public function paymentsByMethod(ReportFilterData $filters): array;
}
