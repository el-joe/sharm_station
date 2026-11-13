<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PriceRule;
use App\Enums\PricingMode;
use App\Enums\ServiceType;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'reference', 'access_token', 'service_type', 'booking_type', 'pricing_mode', 'bookable_type', 'bookable_id', 'season_name',
    'start_date', 'check_in', 'check_out', 'user_id', 'partner_id', 'lead_name', 'lead_email', 'lead_phone', 'adults',
    'children', 'retail_total', 'partner_total', 'commission_amount', 'price_rule', 'total', 'deposit_percent',
    'deposit_amount', 'balance_amount', 'currency', 'exchange_rate', 'total_in_currency', 'status', 'payment_status', 'payment_due_at',
    'locale', 'notes', 'price_lines',
])]
#[Hidden(['access_token'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'booking_type' => BookingType::class,
            'pricing_mode' => PricingMode::class,
            'price_rule' => PriceRule::class,
            'status' => BookingStatus::class,
            'payment_status' => PaymentStatus::class,
            'start_date' => 'immutable_date',
            'check_in' => 'immutable_date',
            'check_out' => 'immutable_date',
            'adults' => 'integer',
            'children' => 'integer',
            'retail_total' => 'decimal:2',
            'partner_total' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'deposit_percent' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'total_in_currency' => 'decimal:2',
            'price_lines' => 'array',
            'payment_due_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Partner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * @return HasMany<BookingTraveler, $this>
     */
    public function travelers(): HasMany
    {
        return $this->hasMany(BookingTraveler::class)->orderBy('sort');
    }

    /**
     * @return HasMany<BookingUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(BookingUnit::class);
    }

    /**
     * @return HasMany<BookingRoom, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(BookingRoom::class);
    }

    /**
     * @return HasMany<BookingAddon, $this>
     */
    public function addons(): HasMany
    {
        return $this->hasMany(BookingAddon::class);
    }

    /**
     * @return HasMany<BookingStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(BookingStatusLog::class)->latest('id');
    }

    /**
     * @return HasMany<BookingIncludedTransfer, $this>
     */
    public function includedTransfers(): HasMany
    {
        return $this->hasMany(BookingIncludedTransfer::class)->orderBy('date')->orderBy('time');
    }

    /**
     * @return HasOne<TransferRequest, $this>
     */
    public function transferRequest(): HasOne
    {
        return $this->hasOne(TransferRequest::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    /**
     * @return HasMany<PaymentProof, $this>
     */
    public function proofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class)->latest('id');
    }

    /**
     * Amount received so far (EUR): the sum of recorded payments when there are any,
     * otherwise derived from the payment status (bookings settled before payments were recorded).
     */
    public function paidAmount(): float
    {
        $payments = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get(['id', 'type', 'status', 'amount']);
        $recorded = $payments->filter(fn (Payment $payment): bool => $payment->status === PaymentRecordStatus::Succeeded);

        if ($recorded->isNotEmpty()) {
            return round($recorded->sum(fn (Payment $payment): float => $payment->type === PaymentType::Refund ? -(float) $payment->amount : (float) $payment->amount), 2);
        }

        return match ($this->payment_status) {
            PaymentStatus::FullyPaid => (float) $this->total,
            PaymentStatus::DepositPaid => (float) $this->deposit_amount,
            default => 0.0,
        };
    }

    /**
     * Amount still to collect (EUR).
     */
    public function dueAmount(): float
    {
        return in_array($this->status, BookingStatus::voided(), true) ? 0.0 : round((float) $this->total - $this->paidAmount(), 2);
    }
}
