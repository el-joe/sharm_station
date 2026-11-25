<?php

namespace App\Services\Notifications;

use App\Enums\NotificationAudience;
use App\Models\Booking;
use App\Models\BookingIncludedTransfer;
use App\Models\Package;
use App\Models\Partner;
use App\Models\Room;
use App\Models\TransferHub;
use App\Models\Trip;
use App\Models\User;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Number;

/**
 * Placeholder values of an email ({{reference}}, {{total}}...), formatted in the recipient's language.
 * Customers see amounts in the booking currency; the admin team sees EUR.
 */
class NotificationContextFactory
{
    public const DEFAULT_BALANCE_DUE_DAYS_BEFORE = 0;

    public function __construct(private readonly SettingRepositoryInterface $settings) {}

    /**
     * @return array<string, string>
     */
    public function company(): array
    {
        return [
            'company_name' => (string) $this->settings->get('site.name', config('app.name')),
            'company_email' => (string) $this->settings->get('site.email', ''),
            'company_phone' => (string) $this->settings->get('site.phone', ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function booking(Booking $booking, string $locale, NotificationAudience $audience): array
    {
        $customerCurrency = $audience !== NotificationAudience::Admin;
        $money = fn (float $amountEur): string => $customerCurrency
            ? $this->money($amountEur * (float) $booking->exchange_rate, $booking->currency, $locale)
            : $this->money($amountEur, 'EUR', $locale);
        $paid = $booking->paidAmount();

        return [
            ...$this->company(),
            'reference' => $booking->reference,
            'customer_name' => (string) $booking->lead_name,
            'service' => $this->serviceName($booking, $locale),
            'service_date' => $this->serviceDate($booking, $locale),
            'total' => $money((float) $booking->total),
            'deposit' => $money((float) $booking->deposit_amount),
            'paid' => $money($paid),
            'balance' => $money(max(0.0, round((float) $booking->total - $paid, 2))),
            'currency' => $customerCurrency ? $booking->currency : 'EUR',
            'status' => $booking->status->label(),
            'payment_due_at' => $this->dateTime($booking->payment_due_at, $locale),
            'balance_due_on' => $this->date($this->balanceDueOn($booking), $locale),
            'booking_url' => $this->bookingUrl($booking, $locale),
            'admin_url' => route('admin.bookings.show', $booking),
            'partner_company' => $booking->partner !== null ? '('.$booking->partner->company.')' : '',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function partner(Partner $partner, string $locale): array
    {
        return [
            ...$this->company(),
            'partner_company' => (string) $partner->company,
            'partner_contact' => (string) ($partner->contact_name ?: $partner->user?->name),
            'partner_email' => (string) $partner->user?->email,
            'partner_phone' => (string) ($partner->phone ?? ''),
            'partner_country' => (string) ($partner->country ?? ''),
            'partner_panel_url' => $this->frontend($locale.'/partner'),
            'statements_url' => $this->frontend($locale.'/partner/statements'),
            'admin_url' => route('admin.partners.show', $partner),
        ];
    }

    /**
     * Website account emails (welcome, password reset): $passwordUrl is the website page that sets the password.
     *
     * @return array<string, string>
     */
    public function account(User $user, string $locale, string $passwordUrl): array
    {
        return [
            ...$this->company(),
            'customer_name' => (string) $user->name,
            'customer_email' => (string) $user->email,
            'password_url' => $passwordUrl,
            'account_url' => $this->frontend($locale.'/account'),
        ];
    }

    /**
     * Website page where the account holder chooses a password: {FRONTEND_URL}/{locale}/reset-password?token=&email=.
     */
    public function passwordUrl(User $user, string $locale, string $token): string
    {
        return $this->frontend($locale.'/reset-password', ['token' => $token, 'email' => $user->email]);
    }

    /**
     * Transfer request or included-transfer tasks of a booking, one line each.
     */
    public function transferDetails(Booking $booking, string $locale): string
    {
        $request = $booking->transferRequest;

        if ($request !== null) {
            return trim(implode("\n", array_filter([
                $request->hub?->translate('name', $locale).' → '.$request->destination_name,
                $this->dateTime($request->pickup_at, $locale),
                $request->vehicleType?->translate('name', $locale),
                $request->passengers ? $request->passengers.' pax' : null,
                $request->flight_number ? 'Flight '.$request->flight_number : null,
            ])));
        }

        return $booking->includedTransfers
            ->map(fn (BookingIncludedTransfer $task): string => '• '.$this->date($task->date, $locale).($task->time ? ' '.substr((string) $task->time, 0, 5) : '')
                .': '.$task->from_place.' → '.$task->to_place.' ('.$task->passengers.' pax)')
            ->implode("\n");
    }

    public function money(float $amount, string $currency, string $locale): string
    {
        return (string) Number::currency(round($amount, 2), $currency, $locale);
    }

    public function date(?CarbonInterface $date, string $locale): string
    {
        return $date?->locale($locale)->isoFormat('LL') ?? '—';
    }

    public function dateTime(?CarbonInterface $date, string $locale): string
    {
        return $date?->locale($locale)->isoFormat('LLL') ?? '—';
    }

    /**
     * Balance due date: the date set by the admin, else the service date minus the "booking.balance_due_days_before" setting.
     */
    public function balanceDueOn(Booking $booking): ?CarbonInterface
    {
        if ($booking->balance_due_on !== null) {
            return $booking->balance_due_on;
        }

        $serviceDate = $booking->start_date ?? $booking->check_in;

        return $serviceDate?->subDays((int) $this->settings->get('booking.balance_due_days_before', self::DEFAULT_BALANCE_DUE_DAYS_BEFORE));
    }

    /**
     * Booking page on the website: the partner panel for partner bookings, otherwise the secure token link.
     */
    public function bookingUrl(Booking $booking, string $locale, array $query = []): string
    {
        if ($booking->partner_id !== null) {
            return $this->frontend("{$locale}/partner/bookings/{$booking->reference}", $query);
        }

        return $this->frontend("{$locale}/booking/{$booking->reference}", ['token' => $booking->access_token, ...$query]);
    }

    public function serviceName(Booking $booking, string $locale): string
    {
        $product = $booking->bookable;

        return match (true) {
            $product instanceof Trip, $product instanceof Package => (string) $product->translate('title', $locale),
            $product instanceof Room => trim($product->building?->translate('name', $locale).' · '.$product->translate('name', $locale), ' ·'),
            $product instanceof TransferHub => 'Transfer '.$product->translate('name', $locale).($booking->transferRequest ? ' → '.$booking->transferRequest->destination_name : ''),
            default => $booking->service_type->label(),
        };
    }

    public function serviceDate(Booking $booking, string $locale): string
    {
        if ($booking->check_in !== null) {
            return $this->date($booking->check_in, $locale).' → '.$this->date($booking->check_out, $locale);
        }

        return $this->date($booking->start_date, $locale);
    }

    /**
     * @param  array<string, string|int>  $query
     */
    public function frontend(string $path, array $query = []): string
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/'.ltrim($path, '/');

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }
}
