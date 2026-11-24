<?php

namespace App\Services;

use App\DTOs\Cancellation\RefundSuggestionData;
use App\Enums\ActivityAction;
use App\Enums\BookingStatus;
use App\Enums\CancellationDecision;
use App\Exceptions\DomainValidationException;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Room;
use App\Models\Trip;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

/**
 * Cancellation requests from the customer, the partner or the booking link (W4), decided by the admin
 * with a refund suggested by the cancellation policy time bands (SRS 12.4).
 */
class CancellationService
{
    /**
     * Default time bands: more than 7 days before the service = full refund of what was paid,
     * 3 to 7 days = 50%, less than 3 days = nothing. Overridable with the "cancellation.bands" setting.
     *
     * @var list<array{min_days: int, percent: float}>
     */
    public const DEFAULT_BANDS = [
        ['min_days' => 8, 'percent' => 100],
        ['min_days' => 3, 'percent' => 50],
        ['min_days' => 0, 'percent' => 0],
    ];

    public function __construct(
        private readonly BookingRepositoryInterface $bookings,
        private readonly BookingService $bookingService,
        private readonly SettingRepositoryInterface $settings,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Records the request once; asking again returns the booking unchanged (idempotent).
     *
     * @return array{booking: Booking, created: bool}
     *
     * @throws DomainValidationException when the booking can no longer be cancelled.
     */
    public function request(Booking $booking, string $reason, ?User $requester): array
    {
        if ($booking->cancellation_requested_at !== null) {
            return ['booking' => $booking, 'created' => false];
        }

        if (in_array($booking->status, [...BookingStatus::voided(), BookingStatus::Completed], true)) {
            throw DomainValidationException::withErrors(['reason' => ['This booking is '.strtolower($booking->status->label()).' and cannot be cancelled.']]);
        }

        return DB::transaction(function () use ($booking, $reason, $requester): array {
            $this->bookings->update($booking, [
                'cancellation_requested_at' => now(),
                'cancellation_reason' => $reason,
                'cancellation_requested_by' => $requester?->id,
            ]);

            $booking->statusLogs()->create([
                'from_status' => $booking->status,
                'to_status' => $booking->status,
                'user_id' => $requester?->id,
                'note' => 'Cancellation requested: '.$reason,
            ]);

            return ['booking' => $booking, 'created' => true];
        });
    }

    /**
     * Suggested refund from the policy bands, applied to what has been paid. A product's own free-cancellation
     * window (hours before the start) gives a full refund.
     */
    public function suggestRefund(Booking $booking): RefundSuggestionData
    {
        $paid = max(0.0, $booking->paidAmount());
        $serviceDate = $booking->start_date ?? $booking->check_in;
        $from = CarbonImmutable::parse($booking->cancellation_requested_at ?? now());

        if ($serviceDate === null) {
            return new RefundSuggestionData(null, 100.0, 'No service date: full refund', $paid, round($paid, 2));
        }

        $start = $serviceDate->startOfDay();
        $hoursBefore = (int) floor($from->diffInHours($start, false));
        $daysBefore = (int) floor($from->startOfDay()->diffInDays($start, false));
        $freeHours = $this->freeCancellationHours($booking);

        if ($freeHours !== null && $hoursBefore >= $freeHours) {
            return new RefundSuggestionData($daysBefore, 100.0, "Free cancellation up to {$freeHours} hours before", $paid, round($paid, 2));
        }

        $bands = $this->bands();

        foreach ($bands as $index => $band) {
            if ($daysBefore >= $band['min_days']) {
                $percent = (float) $band['percent'];

                return new RefundSuggestionData($daysBefore, $percent, $this->bandLabel($band, $bands[$index - 1] ?? null), $paid, round($paid * $percent / 100, 2));
            }
        }

        return new RefundSuggestionData($daysBefore, 0.0, 'After the start date: no refund', $paid, 0.0);
    }

    /**
     * Cancels the booking (status log, rooms released) and stores the refund to pay back.
     *
     * @throws DomainValidationException
     */
    public function approve(Booking $booking, User $actor, float $refundAmount, ?string $note = null): Booking
    {
        $this->ensurePending($booking);
        $paid = max(0.0, $booking->paidAmount());

        if ($refundAmount < 0 || $refundAmount > round($paid + 0.01, 2)) {
            throw DomainValidationException::withErrors(['refund_amount' => ['The refund must be between €0.00 and the amount paid ('.Number::currency($paid, 'EUR', 'en').').']]);
        }

        return DB::transaction(function () use ($booking, $actor, $refundAmount, $note): Booking {
            $this->bookings->update($booking, [
                'cancellation_decision' => CancellationDecision::Approved,
                'cancellation_decided_at' => now(),
                'cancellation_decision_note' => $note,
                'refund_amount' => round($refundAmount, 2),
            ]);

            $this->bookingService->changeStatus($booking, BookingStatus::Cancelled, $actor, trim('Cancellation request approved, refund '.Number::currency($refundAmount, 'EUR', 'en').'. '.($note ?? '')));
            $this->activity->log(ActivityAction::CancellationApproved, $booking, "Cancellation of booking {$booking->reference} approved", ['refund_amount' => round($refundAmount, 2), 'note' => $note], $actor);

            return $booking;
        });
    }

    /**
     * @throws DomainValidationException
     */
    public function decline(Booking $booking, User $actor, string $reason): Booking
    {
        $this->ensurePending($booking);

        return DB::transaction(function () use ($booking, $actor, $reason): Booking {
            $this->bookings->update($booking, [
                'cancellation_decision' => CancellationDecision::Declined,
                'cancellation_decided_at' => now(),
                'cancellation_decision_note' => $reason,
            ]);

            $booking->statusLogs()->create([
                'from_status' => $booking->status,
                'to_status' => $booking->status,
                'user_id' => $actor->id,
                'note' => 'Cancellation request declined: '.$reason,
            ]);

            $this->activity->log(ActivityAction::CancellationDeclined, $booking, "Cancellation request of booking {$booking->reference} declined", ['reason' => $reason], $actor);

            return $booking;
        });
    }

    /**
     * @return list<array{min_days: int, percent: float}>
     */
    public function bands(): array
    {
        $bands = $this->settings->get('cancellation.bands');
        $bands = is_array($bands) && $bands !== [] ? $bands : self::DEFAULT_BANDS;
        usort($bands, fn (array $a, array $b): int => $b['min_days'] <=> $a['min_days']);

        return array_values($bands);
    }

    private function ensurePending(Booking $booking): void
    {
        if (! $booking->hasPendingCancellationRequest()) {
            throw DomainValidationException::withErrors(['cancellation' => ['There is no pending cancellation request on this booking.']]);
        }
    }

    /**
     * @param  array{min_days: int, percent: float}  $band
     * @param  array{min_days: int, percent: float}|null  $previous  the next longer band
     */
    private function bandLabel(array $band, ?array $previous): string
    {
        $percent = (float) $band['percent'];
        $refund = match (true) {
            $percent >= 100 => 'full refund',
            $percent <= 0 => 'no refund',
            default => $percent.'% refund',
        };

        $window = match (true) {
            $previous === null => "{$band['min_days']} or more days before",
            (int) $band['min_days'] === 0 => "Less than {$previous['min_days']} days before",
            default => "{$band['min_days']} to ".($previous['min_days'] - 1).' days before',
        };

        return "{$window}: {$refund}";
    }

    private function freeCancellationHours(Booking $booking): ?int
    {
        $product = $booking->bookable;

        $hours = match (true) {
            $product instanceof Trip, $product instanceof Package => $product->free_cancellation_hours,
            $product instanceof Room => $product->building?->free_cancellation_hours,
            default => null,
        };

        return $hours !== null ? (int) $hours : null;
    }
}
