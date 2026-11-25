<?php

namespace App\DTOs\Payment;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Record refund" on the booking detail (SRS 10.2): full or partial, paid outside the site or sent back through
 * the gateway of the original online payment. Amount in EUR.
 */
final readonly class RecordRefundData
{
    public function __construct(
        public float $amount,
        public CarbonImmutable $paidAt,
        public bool $throughGateway = false,
        public ?int $paymentMethodId = null,
        public ?string $note = null,
        public ?string $transactionId = null,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $data = $request->validated();

        return new self(
            amount: (float) $data['amount'],
            paidAt: CarbonImmutable::parse($data['paid_at'] ?? now()->toDateString()),
            throughGateway: (bool) ($data['through_gateway'] ?? false),
            paymentMethodId: filled($data['payment_method_id'] ?? null) ? (int) $data['payment_method_id'] : null,
            note: $data['note'] ?? null,
            transactionId: $data['transaction_id'] ?? null,
        );
    }
}
