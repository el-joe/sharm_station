<?php

namespace App\Repositories\Contracts;

use App\DTOs\Booking\BookingFilterData;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface BookingRepositoryInterface extends BaseRepositoryInterface
{
    public function findByReference(string $reference): ?Booking;

    public function lastReferenceNumber(): ?int;

    public function paginateForUser(int $userId, int $perPage = 15): LengthAwarePaginator;

    /**
     * Bookings of a partner, newest first, optionally filtered by service, status and payment status.
     *
     * @param  array{service?: string|null, status?: string|null, payment_status?: string|null}  $filters
     * @return Builder<Booking>
     */
    public function partnerQuery(int $partnerId, array $filters = []): Builder;

    /**
     * Filtered admin list query (DataTables and CSV export).
     *
     * @return Builder<Booking>
     */
    public function adminQuery(BookingFilterData $filters): Builder;

    public function countCreatedBetween(CarbonInterface $from, CarbonInterface $to): int;

    /**
     * Sum of a money column over non-voided bookings created in the period (null bounds = all time).
     */
    public function sumCreatedBetween(string $column, ?CarbonInterface $from = null, ?CarbonInterface $to = null, bool $partnerOnly = false): float;

    /**
     * Outstanding balance of live bookings whose deposit is paid.
     */
    public function balanceDue(): float;

    public function countByPaymentStatus(PaymentStatus $status): int;

    /**
     * Bookings created and deposits collected per day.
     *
     * @return array<string, array{bookings: int, deposits: float}> keyed by Y-m-d
     */
    public function dailyTotals(CarbonInterface $from, CarbonInterface $to): array;

    /**
     * @return Collection<int, Booking>
     */
    public function recent(int $limit = 5): Collection;

    /**
     * Oldest booking waiting in the given payment status.
     */
    public function oldestWithPaymentStatus(PaymentStatus $status): ?Booking;

    /**
     * Confirmed bookings with the deposit paid and a balance still to collect, service within the last $pastDays days or later.
     *
     * @return Collection<int, Booking>
     */
    public function withOpenBalance(CarbonInterface $today, int $pastDays = 60): Collection;

    /**
     * Unpaid bookings (status awaiting payment) whose payment deadline has passed.
     *
     * @return Collection<int, Booking>
     */
    public function unpaidPastDeadline(CarbonInterface $now, ?bool $accommodation = null): Collection;

    /**
     * Accommodation bookings awaiting payment whose hold ends between $now and $until.
     *
     * @return Collection<int, Booking>
     */
    public function holdsEndingBetween(CarbonInterface $now, CarbonInterface $until): Collection;

    /**
     * Bookings whose service ended between $from and $to (check-out, else the start date), not voided.
     *
     * @return Collection<int, Booking>
     */
    public function servicesEndedBetween(CarbonInterface $from, CarbonInterface $to): Collection;

    /**
     * Partner totals aggregated in SQL over non-voided bookings created in [$from, $to] (EUR), optionally per creation month.
     * "paid" follows Booking::paidAmount(): succeeded payments minus refunds, else derived from the payment status.
     *
     * @return list<array{period: string|null, bookings_count: int, retail_total: float, commission_amount: float, partner_total: float, total: float, paid: float}>
     */
    public function partnerTotals(int $partnerId, ?CarbonInterface $from = null, ?CarbonInterface $to = null, bool $byMonth = false): array;

    /**
     * Confirmed or fully paid bookings whose service ended before the given day (end date, else check-out/start date).
     *
     * @return Collection<int, Booking>
     */
    public function endedBefore(CarbonInterface $day): Collection;
}
