<?php

namespace App\DTOs\Notification;

use App\Enums\NotificationEvent;
use Illuminate\Database\Eloquent\Model;

/**
 * One email to queue: event, recipient, language, placeholder values and attachments.
 *
 * $attachments: {voucher?: int (booking id), voucher_prices?: bool, statement?: {partner_id: int, period: string}}
 */
final readonly class OutgoingNotificationData
{
    /**
     * @param  array<string, string>  $vars
     * @param  array<string, mixed>  $attachments
     */
    public function __construct(
        public NotificationEvent $event,
        public string $recipient,
        public string $locale,
        public array $vars,
        public ?Model $related = null,
        public ?string $dedupeKey = null,
        public array $attachments = [],
        public ?int $resentFromId = null,
    ) {}
}
