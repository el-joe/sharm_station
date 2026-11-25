<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\NotificationEvent;
use App\Models\Booking;
use App\Models\Room;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Services\Notifications\NotificationContextFactory;
use App\Services\Notifications\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Scheduled jobs of SRS 6.5, 10.1 and 15.1. Every email carries a dedupe key (notification_logs.dedupe_key),
 * so running a job twice never sends twice; expiries are guarded by the booking status.
 */
class ScheduledNotificationService
{
    public const REVIEW_WINDOW_DAYS = 7;

    public const GOOGLE_WRITE_REVIEW_URL = 'https://search.google.com/local/writereview?placeid=';

    public function __construct(
        private readonly BookingRepositoryInterface $bookings,
        private readonly PartnerRepositoryInterface $partners,
        private readonly SettingRepositoryInterface $settings,
        private readonly BookingService $bookingService,
        private readonly NotificationService $notifications,
        private readonly NotificationContextFactory $context,
        private readonly PartnerPortalService $portal,
    ) {}

    /**
     * Reminder N days (setting "notifications.balance_reminder_days") before the balance due date, once per due date.
     */
    public function sendBalanceReminders(?CarbonImmutable $today = null): int
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $days = (int) $this->settings->get('notifications.balance_reminder_days', SettingsService::DEFAULT_BALANCE_REMINDER_DAYS);

        if ($days <= 0) {
            return 0;
        }

        $sent = 0;

        foreach ($this->bookings->withOpenBalance($today) as $booking) {
            $dueOn = $this->context->balanceDueOn($booking);

            if ($dueOn === null || $booking->dueAmount() <= 0 || $today->lt($dueOn->subDays($days)) || $today->gt($dueOn)) {
                continue;
            }

            $sent += count($this->notifications->booking(NotificationEvent::BalanceReminder, $booking, dedupeKey: "balance_reminder:{$booking->id}:{$dueOn->toDateString()}"));
        }

        return $sent;
    }

    /**
     * Daily digest to the admin team of bookings whose balance is past its due date.
     *
     * @return int number of overdue bookings listed (0 = no email)
     */
    public function sendOverdueBalanceAlert(?CarbonImmutable $today = null): int
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $overdue = $this->bookings->withOpenBalance($today, 365)->filter(function (Booking $booking) use ($today): bool {
            $dueOn = $this->context->balanceDueOn($booking);

            return $dueOn !== null && $dueOn->lt($today) && $booking->dueAmount() > 0;
        });

        if ($overdue->isEmpty()) {
            return 0;
        }

        $this->notifications->admins(NotificationEvent::BalanceOverdueAdmin, fn (string $locale): array => [
            ...$this->context->company(),
            'overdue_count' => (string) $overdue->count(),
            'overdue_list' => $overdue->map(fn (Booking $booking): string => '• '.$booking->reference.' · '.$booking->lead_name
                .($booking->partner ? ' ('.$booking->partner->company.')' : '').' · '.$this->context->money($booking->dueAmount(), 'EUR', $locale)
                .' · '.$this->context->date($this->context->balanceDueOn($booking), $locale))->implode("\n"),
            'admin_url' => route('admin.bookings.index'),
        ], dedupeKey: 'balance_overdue:'.$today->toDateString());

        return $overdue->count();
    }

    /**
     * Accommodation holds: "about to expire" email (setting "accommodation.hold_warning_minutes" before the end),
     * then the booking expires and the rooms are released when the hold ends unpaid.
     *
     * @return array{warned: int, expired: int}
     */
    public function processAccommodationHolds(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $minutes = (int) $this->settings->get('accommodation.hold_warning_minutes', SettingsService::DEFAULT_HOLD_WARNING_MINUTES);
        $warned = 0;

        foreach ($this->bookings->holdsEndingBetween($now, $now->addMinutes($minutes)) as $booking) {
            // Holds shorter than the warning window would be warned the moment they are created.
            if ($booking->created_at !== null && $booking->created_at->diffInMinutes($booking->payment_due_at) <= $minutes) {
                continue;
            }

            $locale = $booking->locale === 'it' ? 'it' : 'en';
            $warned += count($this->notifications->booking(NotificationEvent::HoldExpiring, $booking, [
                'hold_expires_at' => $this->context->dateTime($booking->payment_due_at, $locale),
            ], "hold_expiring:{$booking->id}"));
        }

        return ['warned' => $warned, 'expired' => $this->expire($this->bookings->unpaidPastDeadline($now, accommodation: true))];
    }

    /**
     * Trips, packages and transfers not paid by the payment deadline expire (status cancelled + email).
     */
    public function expireUnpaidBookings(?CarbonImmutable $now = null): int
    {
        return $this->expire($this->bookings->unpaidPastDeadline($now ?? CarbonImmutable::now(), accommodation: false));
    }

    /**
     * Post-trip invitation to review on Google, the day after the service ended (within a week, once per booking).
     */
    public function sendReviewInvitations(?CarbonImmutable $today = null): int
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $sent = 0;

        foreach ($this->bookings->servicesEndedBetween($today->subDays(self::REVIEW_WINDOW_DAYS), $today->subDay()) as $booking) {
            $placeId = $this->placeId($booking);

            if ($placeId === null) {
                continue;
            }

            $sent += count($this->notifications->booking(NotificationEvent::ReviewInvitation, $booking, [
                'review_url' => self::GOOGLE_WRITE_REVIEW_URL.rawurlencode($placeId),
            ], "review_invitation:{$booking->id}"));
        }

        return $sent;
    }

    /**
     * On the 1st: last month's statement (with the PDF) to every active partner that had bookings and wants it.
     */
    public function sendPartnerStatements(?CarbonImmutable $today = null): int
    {
        $period = ($today ?? CarbonImmutable::today())->subMonthNoOverflow()->format('Y-m');
        $sent = 0;

        foreach ($this->partners->activeWithStatementEmails() as $partner) {
            $statement = $this->portal->statement($partner, $period);

            if ($statement['bookings'] === []) {
                continue;
            }

            $summary = $statement['summary'];
            $locale = $partner->user?->locale === 'it' ? 'it' : 'en';
            $log = $this->notifications->partner(NotificationEvent::PartnerStatement, $partner, [
                'period' => CarbonImmutable::createFromFormat('Y-m-d', $period.'-01')->locale($locale)->isoFormat('MMMM YYYY'),
                'statement_bookings' => (string) $summary['bookings_count'],
                'statement_total' => $this->context->money((float) $summary['partner_total'], 'EUR', $locale),
                'statement_commission' => $this->context->money((float) $summary['commission_amount'], 'EUR', $locale),
                'statement_balance' => $this->context->money((float) $summary['balance'], 'EUR', $locale),
            ], "partner_statement:{$partner->id}:{$period}", ['statement' => ['partner_id' => $partner->id, 'period' => $period]]);

            $sent += $log !== null ? 1 : 0;
        }

        return $sent;
    }

    /**
     * @param  iterable<Booking>  $bookings
     */
    private function expire(iterable $bookings): int
    {
        $expired = 0;

        foreach ($bookings as $booking) {
            $changed = DB::transaction(function () use ($booking): bool {
                $fresh = $this->bookings->query()->lockForUpdate()->find($booking->id);

                if ($fresh === null || $fresh->status !== BookingStatus::AwaitingPayment) {
                    return false;
                }

                $this->bookingService->changeStatus($fresh, BookingStatus::Cancelled, null, 'Payment deadline expired');

                return true;
            });

            if ($changed) {
                $this->notifications->booking(NotificationEvent::BookingExpired, $booking->refresh(), dedupeKey: "booking_expired:{$booking->id}");
                $expired++;
            }
        }

        return $expired;
    }

    private function placeId(Booking $booking): ?string
    {
        $product = $booking->bookable;
        $placeId = $product instanceof Room ? $product->building?->google_place_id : null;
        $placeId = filled($placeId) ? $placeId : $this->settings->get('google_reviews.place_id', config('services.google.place_id'));

        return filled($placeId) ? (string) $placeId : null;
    }
}
