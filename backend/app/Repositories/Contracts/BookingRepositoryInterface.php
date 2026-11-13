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
}
