<?php

namespace App\Repositories\Eloquent;

use App\Models\TransferRequest;
use App\Repositories\Contracts\TransferRequestRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<TransferRequest>
 */
class TransferRequestRepository extends BaseRepository implements TransferRequestRepositoryInterface
{
    protected function model(): string
    {
        return TransferRequest::class;
    }

    public function adminQuery(?string $status = null): Builder
    {
        return $this->query()
            ->select('transfer_requests.*')
            ->with(['booking:id,reference,lead_name,lead_phone,payment_status', 'hub:id,name', 'vehicleType:id,name'])
            ->when($status, fn (Builder $query, string $status) => $query->where('status', $status));
    }
}
