<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentStatus: string
{
    use HasOptions;

    case AwaitingPayment = 'awaiting_payment';
    case AwaitingReceiptReview = 'awaiting_receipt_review';
    case ProofRejected = 'proof_rejected';
    case DepositPaid = 'deposit_paid';
    case FullyPaid = 'fully_paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Awaiting payment',
            self::AwaitingReceiptReview => 'Awaiting receipt review',
            self::ProofRejected => 'Proof rejected',
            self::DepositPaid => 'Deposit paid',
            self::FullyPaid => 'Fully paid',
            self::Failed => 'Failed',
            self::Refunded => 'Refunded',
        };
    }
}
