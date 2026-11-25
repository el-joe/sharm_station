<?php

namespace App\Services\Notifications;

use App\DTOs\Notification\NotificationLogFilterData;
use App\DTOs\Notification\NotificationTemplateData;
use App\DTOs\Notification\OutgoingNotificationData;
use App\DTOs\Notification\RenderedNotificationData;
use App\Enums\NotificationAudience;
use App\Enums\NotificationEvent;
use App\Enums\NotificationStatus;
use App\Mail\NotificationMail;
use App\Models\Booking;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Partner;
use App\Models\User;
use App\Repositories\Contracts\NotificationLogRepositoryInterface;
use App\Repositories\Contracts\NotificationTemplateRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Email notifications (SRS 15.1, 14.1): picks the recipients and language, renders the event template
 * (the admin's template when stored, else the default text), writes the sending log and queues the email.
 * Called by the domain services, never by controllers.
 */
class NotificationService
{
    public function __construct(
        private readonly NotificationTemplateRepositoryInterface $templates,
        private readonly NotificationLogRepositoryInterface $logs,
        private readonly SettingRepositoryInterface $settings,
        private readonly NotificationContextFactory $context,
        private readonly TemplateRenderer $renderer,
        private readonly PasswordBrokerFactory $passwords,
    ) {}

    /**
     * Booking email to the booker (customer, or the partner account for partner bookings) or to the admin team,
     * depending on the event's audience. $dedupeKey prevents a second send (scheduled jobs).
     *
     * @param  array<string, string>  $extra  additional placeholder values
     * @return list<NotificationLog>
     */
    public function booking(NotificationEvent $event, Booking $booking, array $extra = [], ?string $dedupeKey = null): array
    {
        try {
            $booking->loadMissing(['partner.user', 'user', 'bookable']);

            if ($event->audience() === NotificationAudience::Admin) {
                return $this->admins($event, fn (string $locale): array => [...$this->context->booking($booking, $locale, NotificationAudience::Admin), ...$extra], $booking, $dedupeKey);
            }

            $recipient = $this->bookerEmail($booking);

            if ($recipient === null) {
                return [];
            }

            $locale = TemplateRenderer::locale($booking->partner_id !== null ? ($booking->partner?->user?->locale ?? $booking->locale) : $booking->locale);
            $log = $this->queue(new OutgoingNotificationData(
                event: $event,
                recipient: $recipient,
                locale: $locale,
                vars: [...$this->context->booking($booking, $locale, NotificationAudience::Customer), ...$extra],
                related: $booking,
                dedupeKey: $dedupeKey,
                attachments: $event->attachesVoucher() ? ['voucher' => $booking->id, 'voucher_prices' => true] : [],
            ));

            return $log !== null ? [$log] : [];
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * Email to a partner account (application decision, monthly statement).
     *
     * @param  array<string, string>  $extra
     * @param  array<string, mixed>  $attachments
     */
    public function partner(NotificationEvent $event, Partner $partner, array $extra = [], ?string $dedupeKey = null, array $attachments = []): ?NotificationLog
    {
        try {
            $partner->loadMissing('user');
            $recipient = $partner->user?->email;

            if (blank($recipient)) {
                return null;
            }

            $locale = TemplateRenderer::locale($partner->user?->locale);

            return $this->queue(new OutgoingNotificationData($event, (string) $recipient, $locale, [...$this->context->partner($partner, $locale), ...$extra], $partner, $dedupeKey, $attachments));
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Account email to a website user (welcome, password reset) with a fresh password link: the token is issued by the
     * event's password broker and never stored in the log (the logged link is redacted; a resend issues a new token).
     */
    public function account(NotificationEvent $event, User $user, ?int $resentFromId = null): ?NotificationLog
    {
        try {
            $locale = TemplateRenderer::locale($user->locale);

            if (! $event->isAccountEvent() || blank($user->email) || ! $this->template($event, $locale)['enabled']) {
                return null;
            }

            $token = $this->passwords->broker($event->passwordBroker())->createToken($user);
            $vars = $this->context->account($user, $locale, $this->context->passwordUrl($user, $locale, $token));

            return $this->queue(new OutgoingNotificationData($event, (string) $user->email, $locale, $vars, $user, resentFromId: $resentFromId));
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Email to every admin address (setting "notifications.admin_emails", else the company email).
     *
     * @param  callable(string): array<string, string>  $vars  locale => placeholder values
     * @return list<NotificationLog>
     */
    public function admins(NotificationEvent $event, callable $vars, ?Model $related = null, ?string $dedupeKey = null): array
    {
        $locale = TemplateRenderer::locale((string) $this->settings->get('notifications.admin_locale', config('app.locale')));
        $values = $vars($locale);
        $logs = [];

        foreach ($this->adminRecipients() as $recipient) {
            $log = $this->queue(new OutgoingNotificationData($event, $recipient, $locale, $values, $related, $dedupeKey !== null ? $dedupeKey.'|'.$recipient : null));

            if ($log !== null) {
                $logs[] = $log;
            }
        }

        return $logs;
    }

    /**
     * Writes the log row and queues the email. Returns null when the event is disabled or already sent (dedupe key).
     */
    public function queue(OutgoingNotificationData $outgoing): ?NotificationLog
    {
        $template = $this->template($outgoing->event, $outgoing->locale);

        if (! $template['enabled'] || ($outgoing->dedupeKey !== null && $this->logs->dedupeKeyExists($outgoing->dedupeKey))) {
            return null;
        }

        $rendered = $this->renderer->render($outgoing->event, $outgoing->locale, $template['subject'], $template['body'], $outgoing->vars);

        try {
            /** @var NotificationLog $log */
            $log = $this->logs->create([
                'event' => $outgoing->event,
                'locale' => $outgoing->locale,
                'recipient' => $outgoing->recipient,
                'subject' => mb_substr($rendered->subject, 0, 250),
                'status' => NotificationStatus::Queued,
                'related_type' => $outgoing->related?->getMorphClass(),
                'related_id' => $outgoing->related?->getKey(),
                'context' => ['vars' => $this->loggableVars($outgoing->event, $outgoing->vars), 'attachments' => $outgoing->attachments],
                'dedupe_key' => $outgoing->dedupeKey,
                'resent_from_id' => $outgoing->resentFromId,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        $this->dispatch($log, $rendered, $outgoing->attachments);

        return $log;
    }

    /**
     * Sends a logged email again (current template, same values and attachments) as a new log row.
     */
    public function resend(NotificationLog $original): ?NotificationLog
    {
        if ($original->event->isAccountEvent()) {
            $user = $original->related;

            return $user instanceof User ? $this->account($original->event, $user, $original->id) : null;
        }

        $context = $original->context ?? [];
        $template = $this->template($original->event, $original->locale);
        $rendered = $this->renderer->render($original->event, $original->locale, $template['subject'], $template['body'], $context['vars'] ?? []);

        /** @var NotificationLog $log */
        $log = $this->logs->create([
            'event' => $original->event,
            'locale' => $original->locale,
            'recipient' => $original->recipient,
            'subject' => mb_substr($rendered->subject, 0, 250),
            'status' => NotificationStatus::Queued,
            'related_type' => $original->related_type,
            'related_id' => $original->related_id,
            'context' => $context,
            'resent_from_id' => $original->id,
        ]);

        $this->dispatch($log, $rendered, $context['attachments'] ?? []);

        return $log;
    }

    /**
     * Sends the (unsaved) template with sample values to the signed-in admin.
     */
    public function sendTest(NotificationEvent $event, string $locale, NotificationTemplateData $data, User $to): NotificationLog
    {
        $rendered = $this->preview($event, $locale, $data);

        /** @var NotificationLog $log */
        $log = $this->logs->create([
            'event' => $event,
            'locale' => $locale,
            'recipient' => $to->email,
            'subject' => mb_substr('[TEST] '.$rendered->subject, 0, 250),
            'status' => NotificationStatus::Queued,
            'related_type' => $to->getMorphClass(),
            'related_id' => $to->id,
            'context' => ['vars' => $this->sampleVars($event, $locale), 'test' => true],
        ]);

        $this->dispatch($log, new RenderedNotificationData('[TEST] '.$rendered->subject, $rendered->html, $rendered->text, $rendered->actionLabel, $rendered->actionUrl), []);

        return $log;
    }

    public function preview(NotificationEvent $event, string $locale, ?NotificationTemplateData $data = null): RenderedNotificationData
    {
        $template = $data !== null ? ['subject' => $data->subject, 'body' => $data->body] : $this->template($event, $locale);

        return $this->renderer->render($event, $locale, $template['subject'], $template['body'], $this->sampleVars($event, $locale));
    }

    /**
     * Branded HTML of the preview, as the recipient sees it.
     */
    public function previewHtml(NotificationEvent $event, string $locale, ?NotificationTemplateData $data = null): string
    {
        return (new NotificationMail($event, $this->preview($event, $locale, $data), $locale, companyName: $this->context->company()['company_name']))->render();
    }

    /**
     * Subject, body and enabled flag: the stored template, else the default text of the event.
     *
     * @return array{subject: string, body: string, enabled: bool, stored: bool}
     */
    public function template(NotificationEvent $event, string $locale): array
    {
        $stored = $this->templates->findFor($event, $locale);

        if ($stored !== null) {
            return ['subject' => $stored->subject, 'body' => $stored->body, 'enabled' => $stored->is_enabled, 'stored' => true];
        }

        $default = $this->renderer->defaults($event, $locale);

        return ['subject' => $default['subject'], 'body' => $default['body'], 'enabled' => true, 'stored' => false];
    }

    public function saveTemplate(NotificationEvent $event, string $locale, NotificationTemplateData $data): NotificationTemplate
    {
        $template = $this->templates->findFor($event, $locale);

        return $template !== null
            ? $this->templates->update($template, $data->toAttributes())
            : $this->templates->create(['event' => $event, 'locale' => $locale, ...$data->toAttributes()]);
    }

    /**
     * Default templates for every event and language; existing rows are left as the admin edited them.
     */
    public function seedDefaults(): int
    {
        $created = 0;

        foreach (NotificationEvent::cases() as $event) {
            foreach (TemplateRenderer::SUPPORTED_LOCALES as $locale) {
                if ($this->templates->findFor($event, $locale) === null) {
                    $default = $this->renderer->defaults($event, $locale);
                    $this->templates->create(['event' => $event, 'locale' => $locale, 'subject' => $default['subject'], 'body' => $default['body'], 'is_enabled' => true]);
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * @return Collection<int, NotificationTemplate>
     */
    public function templates(): Collection
    {
        return $this->templates->allOrdered();
    }

    /**
     * @return Builder<NotificationLog>
     */
    public function logsQuery(NotificationLogFilterData $filters): Builder
    {
        return $this->logs->adminQuery($filters);
    }

    /**
     * @return list<string>
     */
    public function unknownPlaceholders(NotificationEvent $event, string $text): array
    {
        return $this->renderer->unknownPlaceholders($event, $text);
    }

    /**
     * @return list<string>
     */
    public function adminRecipients(): array
    {
        $emails = $this->settings->get('notifications.admin_emails');
        $emails = is_array($emails) && $emails !== [] ? $emails : array_filter([(string) $this->settings->get('site.email', '')]);

        return array_values(array_unique(array_filter(array_map('trim', $emails), fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)));
    }

    public function dedupeKeyExists(string $key): bool
    {
        return $this->logs->dedupeKeyExists($key);
    }

    /**
     * Realistic sample values for previews and test emails.
     *
     * @return array<string, string>
     */
    public function sampleVars(NotificationEvent $event, string $locale): array
    {
        $money = fn (float $amount): string => $this->context->money($amount, 'EUR', $locale);
        $date = fn (int $days): string => $this->context->date(now()->addDays($days), $locale);

        $samples = [
            ...$this->context->company(),
            'reference' => 'BK-2041', 'customer_name' => 'Maria Rossi', 'service' => 'Ras Mohammed snorkelling',
            'service_date' => $date(14), 'total' => $money(250), 'deposit' => $money(75), 'paid' => $money(75), 'balance' => $money(175),
            'currency' => 'EUR', 'status' => 'Confirmed', 'payment_due_at' => $this->context->dateTime(now()->addDays(2), $locale),
            'balance_due_on' => $date(12), 'booking_url' => $this->context->frontend($locale.'/booking/BK-2041', ['token' => 'sample']),
            'admin_url' => url('/admin/bookings'), 'reason' => 'The receipt is not readable.',
            'refund_amount' => $money(75), 'hold_expires_at' => $this->context->dateTime(now()->addMinutes(10), $locale),
            'transfer_status' => 'Confirmed', 'transfer_details' => "Sharm El Sheikh Airport → Rixos Premium Seagate\n".$this->context->dateTime(now()->addDays(14), $locale)."\nMinivan · 4 pax",
            'driver_name' => 'Ahmed', 'driver_phone' => '+20 100 000 0000', 'car_plate' => 'ABC 123',
            'review_url' => 'https://search.google.com/local/writereview?placeid=sample', 'overdue_count' => '2',
            'overdue_list' => '• BK-2041 · Maria Rossi · '.$money(175).' · due '.$date(-2)."\n• BK-2050 · John Smith · ".$money(90).' · due '.$date(-5),
            'partner_company' => 'Blue Sea Travel', 'partner_contact' => 'Luca Bianchi', 'partner_email' => 'luca@example.com',
            'partner_phone' => '+39 333 000 0000', 'partner_country' => 'Italy', 'partner_panel_url' => $this->context->frontend($locale.'/partner'),
            'period' => now()->subMonth()->locale($locale)->isoFormat('MMMM YYYY'), 'statement_bookings' => '8', 'statement_total' => $money(1840),
            'statement_commission' => $money(276), 'statement_balance' => $money(520), 'statements_url' => $this->context->frontend($locale.'/partner/statements'),
            'customer_email' => 'maria.rossi@example.com', 'account_url' => $this->context->frontend($locale.'/account'),
            'password_url' => $this->context->frontend($locale.'/reset-password', ['token' => 'sample', 'email' => 'maria.rossi@example.com']),
        ];

        return array_intersect_key($samples, array_flip($event->placeholders()));
    }

    /**
     * Values stored in the log: password links are secrets, so account emails keep only a redacted copy.
     *
     * @param  array<string, string>  $vars
     * @return array<string, string>
     */
    private function loggableVars(NotificationEvent $event, array $vars): array
    {
        return $event->isAccountEvent() && isset($vars['password_url']) ? [...$vars, 'password_url' => '[redacted]'] : $vars;
    }

    /**
     * @param  array<string, mixed>  $attachments
     */
    private function dispatch(NotificationLog $log, RenderedNotificationData $rendered, array $attachments): void
    {
        Mail::to($log->recipient)->queue(new NotificationMail(
            event: $log->event,
            content: $rendered,
            mailLocale: $log->locale,
            logId: $log->id,
            files: $attachments,
            companyName: $this->context->company()['company_name'],
        ));
    }

    private function bookerEmail(Booking $booking): ?string
    {
        if ($booking->partner_id !== null) {
            $email = $booking->partner?->notify_bookings !== false ? $booking->partner?->user?->email : null;
        } else {
            $email = $booking->lead_email;
        }

        return filled($email) ? (string) $email : null;
    }
}
