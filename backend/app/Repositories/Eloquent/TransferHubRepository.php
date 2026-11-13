<?php

namespace App\Repositories\Eloquent;

use App\Models\TransferHub;
use App\Repositories\Contracts\TransferHubRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<TransferHub>
 */
class TransferHubRepository extends BaseRepository implements TransferHubRepositoryInterface
{
    protected function model(): string
    {
        return TransferHub::class;
    }

    public function enabled(): Collection
    {
        return $this->query()->enabled()->with(['destination', 'fixedRoutes' => fn ($query) => $query->where('is_enabled', true)])->orderBy('sort')->get();
    }

    public function findEnabled(int $id): ?TransferHub
    {
        return $this->query()->enabled()->find($id);
    }
}
