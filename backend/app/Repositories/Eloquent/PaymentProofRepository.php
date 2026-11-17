<?php

namespace App\Repositories\Eloquent;

use App\Enums\ProofStatus;
use App\Models\PaymentProof;
use App\Repositories\Contracts\PaymentProofRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<PaymentProof>
 */
class PaymentProofRepository extends BaseRepository implements PaymentProofRepositoryInterface
{
    protected function model(): string
    {
        return PaymentProof::class;
    }

    public function adminQuery(?string $status = null): Builder
    {
        return $this->query()
            ->select('payment_proofs.*')
            ->with(['booking:id,reference,lead_name,currency,deposit_amount,total', 'method:id,name', 'reviewer:id,name'])
            ->when($status, fn (Builder $query, string $status) => $query->where('status', $status));
    }

    public function countPendingForPartner(int $partnerId): int
    {
        return $this->query()
            ->where('status', ProofStatus::Pending)
            ->whereHas('booking', fn (Builder $booking) => $booking->where('partner_id', $partnerId))
            ->count();
    }
}
