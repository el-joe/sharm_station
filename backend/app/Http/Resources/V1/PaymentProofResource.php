<?php

namespace App\Http\Resources\V1;

use App\Models\PaymentProof;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Proof status for the customer. File paths stay private.
 *
 * @mixin PaymentProof
 */
class PaymentProofResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'purpose' => $this->purpose->value,
            'payment_method_id' => $this->payment_method_id,
            'files' => array_map(fn (array $file): array => ['name' => $file['name'], 'mime' => $file['mime'], 'size' => $file['size']], $this->files ?? []),
            'transaction_number' => $this->transaction_number,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'amount' => $this->amount !== null ? (float) $this->amount : null,
            'sender_name' => $this->sender_name,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
