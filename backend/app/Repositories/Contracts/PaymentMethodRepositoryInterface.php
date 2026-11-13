<?php

namespace App\Repositories\Contracts;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<PaymentMethod>
 */
interface PaymentMethodRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Enabled methods that accept the currency, in display order.
     *
     * @return Collection<int, PaymentMethod>
     */
    public function enabledForCurrency(string $currency): Collection;

    public function findEnabled(int $id): ?PaymentMethod;

    /**
     * @return Builder<PaymentMethod>
     */
    public function adminQuery(): Builder;
}
