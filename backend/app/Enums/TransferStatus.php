<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Operational status of transfer requests and included-transfer tasks (SRS 5.5).
 */
enum TransferStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case AwaitingPayment = 'awaiting_payment';
    case AwaitingReceiptReview = 'awaiting_receipt_review';
    case Paid = 'paid';
    case BeingArranged = 'being_arranged';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::AwaitingPayment => 'Awaiting payment',
            self::AwaitingReceiptReview => 'Awaiting receipt review',
            self::Paid => 'Paid',
            self::BeingArranged => 'Being arranged',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }
}
