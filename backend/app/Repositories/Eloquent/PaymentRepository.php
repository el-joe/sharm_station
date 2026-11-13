<?php

namespace App\Repositories\Eloquent;

use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<Payment>
 */
class PaymentRepository extends BaseRepository implements PaymentRepositoryInterface
{
    protected function model(): string
    {
        return Payment::class;
    }

    public function findPendingByGatewayReference(string $reference): ?Payment
    {
        return $this->query()->where('gateway_reference', $reference)->with('booking')->first();
    }

    public function adminQuery(?string $status = null): Builder
    {
        return $this->query()
            ->select('payments.*')
            ->with(['booking:id,reference,lead_name', 'method:id,name', 'recorder:id,name'])
            ->when($status, fn (Builder $query, string $status) => $query->where('status', $status));
    }
}
