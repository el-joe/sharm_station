<?php

namespace App\Models;

use App\Enums\PaymentType;
use App\Enums\ProofStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Proof of a manual payment (SRS 10.3). Files live on the private disk:
 * files = list of {path: string, name: string, mime: string, size: int}.
 */
#[Fillable([
    'booking_id', 'payment_method_id', 'payment_id', 'purpose', 'files', 'transaction_number', 'transaction_date', 'amount',
    'sender_name', 'status', 'uploaded_by', 'reviewed_by', 'reviewed_at', 'rejection_reason',
])]
class PaymentProof extends Model
{
    public const DISK = 'local';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => PaymentType::class,
            'status' => ProofStatus::class,
            'files' => 'array',
            'transaction_date' => 'immutable_date',
            'amount' => 'decimal:2',
            'reviewed_at' => 'immutable_datetime',
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
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return array{path: string, name: string, mime: string, size: int}|null
     */
    public function file(int $index): ?array
    {
        return $this->files[$index] ?? null;
    }
}
