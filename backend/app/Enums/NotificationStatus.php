<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Delivery status of one email in the sending log.
 */
enum NotificationStatus: string
{
    use HasOptions;

    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
        };
    }
}
