<?php

namespace App\Repositories\Contracts;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<Currency>
 */
interface CurrencyRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return Collection<int, Currency>
     */
    public function enabled(): Collection;
}
