<?php

namespace App\Models;

use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment attempt (online) or a recorded payment (approved proof, balance recorded by the admin). Amount in EUR.
 */
#[Fillable([
    'booking_id', 'payment_method_id', 'type', 'status', 'amount', 'currency', 'exchange_rate', 'amount_in_currency',
    'transaction_id', 'gateway_reference', 'paid_at', 'recorded_by', 'note', 'meta',
])]
class Payment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PaymentType::class,
            'status' => PaymentRecordStatus::class,
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'amount_in_currency' => 'decimal:2',
            'paid_at' => 'immutable_datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
