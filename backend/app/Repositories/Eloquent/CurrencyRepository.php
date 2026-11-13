<?php

namespace App\Repositories\Eloquent;

use App\Models\Currency;
use App\Repositories\Contracts\CurrencyRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Currency>
 */
class CurrencyRepository extends BaseRepository implements CurrencyRepositoryInterface
{
    protected function model(): string
    {
        return Currency::class;
    }

    public function enabled(): Collection
    {
        return $this->query()->where('is_enabled', true)->orderBy('sort')->get();
    }
}
