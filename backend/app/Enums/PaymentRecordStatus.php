<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Status of a single payment attempt or recorded payment.
 */
enum PaymentRecordStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
            self::Refunded => 'Refunded',
        };
    }
}
