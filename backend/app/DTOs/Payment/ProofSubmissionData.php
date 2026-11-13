<?php

namespace App\DTOs\Payment;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final readonly class ProofSubmissionData
{
    /**
     * @param  list<UploadedFile>  $files
     */
    public function __construct(
        public int $paymentMethodId,
        public array $files,
        public ?string $transactionNumber = null,
        public ?CarbonImmutable $transactionDate = null,
        public ?float $amount = null,
        public ?string $senderName = null,
        public ?int $uploadedBy = null,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $data = $request->validated();

        return new self(
            paymentMethodId: (int) $data['payment_method_id'],
            files: array_values($request->file('files', [])),
            transactionNumber: $data['transaction_number'] ?? null,
            transactionDate: filled($data['transaction_date'] ?? null) ? CarbonImmutable::parse($data['transaction_date']) : null,
            amount: filled($data['amount'] ?? null) ? (float) $data['amount'] : null,
            senderName: $data['sender_name'] ?? null,
            uploadedBy: $request->user('sanctum')?->getKey() ?? $request->user()?->getKey(),
        );
    }
}
