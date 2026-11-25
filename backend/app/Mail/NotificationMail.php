<?php

namespace App\Mail;

use App\DTOs\Notification\RenderedNotificationData;
use App\Enums\NotificationAudience;
use App\Enums\NotificationEvent;
use App\Enums\NotificationStatus;
use App\Models\Booking;
use App\Models\NotificationLog;
use App\Models\Partner;
use App\Services\PartnerStatementExporter;
use App\Services\VoucherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\SentMessage;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Every notification email (SRS 15.1): the text comes from the event template already rendered by
 * NotificationService, wrapped in the branded layout. Queued after the database commit; the sending log row
 * is marked sent or failed (a failure is logged, never thrown back to the booking flow; the admin can resend).
 *
 * $files: {voucher?: int (booking id), voucher_prices?: bool, statement?: {partner_id: int, period: string}}
 */
class NotificationMail extends Mailable implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * @param  array<string, mixed>  $files
     */
    public function __construct(
        public NotificationEvent $event,
        public RenderedNotificationData $content,
        public string $mailLocale,
        public ?int $logId = null,
        public array $files = [],
        public string $companyName = '',
    ) {
        $this->locale($mailLocale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->content->subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.notification',
            text: 'mail.notification-text',
            with: [
                'rendered' => $this->content,
                'company' => $this->companyName !== '' ? $this->companyName : config('app.name'),
                'isAdmin' => $this->event->audience() === NotificationAudience::Admin,
                'footerKey' => match (true) {
                    $this->event->audience() === NotificationAudience::Admin => 'notifications.layout.footer_admin',
                    $this->event->isAccountEvent() => 'notifications.layout.footer_account',
                    default => 'notifications.layout.footer',
                },
                'hasVoucher' => isset($this->files['voucher']),
                'mailLocale' => $this->mailLocale,
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        if (isset($this->files['voucher']) && ($booking = Booking::query()->find($this->files['voucher'])) !== null) {
            $withPrices = (bool) ($this->files['voucher_prices'] ?? true);
            $attachments[] = Attachment::fromData(fn (): string => app(VoucherService::class)->render($booking, $withPrices, $this->mailLocale), 'voucher-'.$booking->reference.'.pdf')
                ->withMime('application/pdf');
        }

        if (isset($this->files['statement']) && ($partner = Partner::query()->find($this->files['statement']['partner_id'] ?? 0)) !== null) {
            $period = (string) $this->files['statement']['period'];
            $attachments[] = Attachment::fromData(fn (): string => app(PartnerStatementExporter::class)->pdfContent($partner, $period), 'statement-'.$period.'.pdf')
                ->withMime('application/pdf');
        }

        return $attachments;
    }

    /**
     * @param  Factory|Mailer  $mailer
     */
    public function send($mailer): ?SentMessage
    {
        try {
            $sent = parent::send($mailer);
            $this->mark(NotificationStatus::Sent);

            return $sent;
        } catch (Throwable $exception) {
            $this->mark(NotificationStatus::Failed, $exception->getMessage());
            report($exception);

            return null;
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->mark(NotificationStatus::Failed, $exception->getMessage());
    }

    private function mark(NotificationStatus $status, ?string $error = null): void
    {
        if ($this->logId === null) {
            return;
        }

        NotificationLog::query()->whereKey($this->logId)->update([
            'status' => $status,
            'error' => $error !== null ? mb_substr($error, 0, 2000) : null,
            'sent_at' => $status === NotificationStatus::Sent ? now() : null,
            'updated_at' => now(),
        ]);
    }
}
