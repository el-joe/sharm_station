<?php

namespace App\Repositories\Eloquent;

use App\Models\PaymentMethod;
use App\Repositories\Contracts\PaymentMethodRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<PaymentMethod>
 */
class PaymentMethodRepository extends BaseRepository implements PaymentMethodRepositoryInterface
{
    protected function model(): string
    {
        return PaymentMethod::class;
    }

    public function enabledForCurrency(string $currency): Collection
    {
        return $this->query()->enabled()->orderBy('sort')->orderBy('id')->get()
            ->filter(fn (PaymentMethod $method): bool => $method->supportsCurrency($currency))
            ->values();
    }

    public function findEnabled(int $id): ?PaymentMethod
    {
        return $this->query()->enabled()->find($id);
    }

    public function adminQuery(): Builder
    {
        return $this->query()->select('payment_methods.*');
    }
}
