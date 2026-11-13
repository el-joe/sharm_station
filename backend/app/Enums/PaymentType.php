<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentType: string
{
    use HasOptions;

    case Deposit = 'deposit';
    case Balance = 'balance';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Deposit',
            self::Balance => 'Balance',
            self::Refund => 'Refund',
        };
    }
}
