<?php

namespace App\Repositories\Contracts;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepositoryInterface<Payment>
 */
interface PaymentRepositoryInterface extends BaseRepositoryInterface
{
    public function findPendingByGatewayReference(string $reference): ?Payment;

    /**
     * @return Builder<Payment>
     */
    public function adminQuery(?string $status = null): Builder;
}
