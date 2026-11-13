<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum BookingStatus: string
{
    use HasOptions;

    case AwaitingPayment = 'awaiting_payment';
    case AwaitingReceiptReview = 'awaiting_receipt_review';
    case Confirmed = 'confirmed';
    case FullyPaid = 'fully_paid';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Awaiting payment',
            self::AwaitingReceiptReview => 'Awaiting receipt review',
            self::Confirmed => 'Confirmed',
            self::FullyPaid => 'Fully paid',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
        };
    }

    /**
     * Statuses that do not count towards revenue.
     *
     * @return list<self>
     */
    public static function voided(): array
    {
        return [self::Cancelled, self::Refunded];
    }
}
