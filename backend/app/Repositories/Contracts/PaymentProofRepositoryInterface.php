<?php

namespace App\Repositories\Contracts;

use App\Models\PaymentProof;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepositoryInterface<PaymentProof>
 */
interface PaymentProofRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return Builder<PaymentProof>
     */
    public function adminQuery(?string $status = null): Builder;

    /**
     * Pending payment proofs of a partner's bookings.
     */
    public function countPendingForPartner(int $partnerId): int;
}
