<?php

namespace App\Repositories\Contracts;

use App\Models\TransferRequest;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepositoryInterface<TransferRequest>
 */
interface TransferRequestRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return Builder<TransferRequest>
     */
    public function adminQuery(?string $status = null): Builder;
}
