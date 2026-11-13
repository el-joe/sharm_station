<?php

namespace App\DTOs\Payment;

use App\Enums\PaymentType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A payment recorded manually by the admin (usually the balance, paid outside the site). Amount in EUR.
 */
final readonly class RecordPaymentData
{
    public function __construct(
        public float $amount,
        public CarbonImmutable $paidAt,
        public ?int $paymentMethodId = null,
        public ?string $note = null,
        public PaymentType $type = PaymentType::Balance,
        public ?string $transactionId = null,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $data = $request->validated();

        return new self(
            amount: (float) $data['amount'],
            paidAt: CarbonImmutable::parse($data['paid_at']),
            paymentMethodId: filled($data['payment_method_id'] ?? null) ? (int) $data['payment_method_id'] : null,
            note: $data['note'] ?? null,
            type: PaymentType::from($data['type'] ?? PaymentType::Balance->value),
            transactionId: $data['transaction_id'] ?? null,
        );
    }
}
