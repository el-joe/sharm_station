<?php

namespace App\Repositories\Contracts;

use App\Models\TransferHub;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<TransferHub>
 */
interface TransferHubRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return Collection<int, TransferHub>
     */
    public function enabled(): Collection;

    public function findEnabled(int $id): ?TransferHub;
}
