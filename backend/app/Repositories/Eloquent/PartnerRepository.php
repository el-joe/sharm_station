<?php

namespace App\Repositories\Eloquent;

use App\Enums\BookingStatus;
use App\Enums\PartnerStatus;
use App\Models\Partner;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Partner>
 */
class PartnerRepository extends BaseRepository implements PartnerRepositoryInterface
{
    protected function model(): string
    {
        return Partner::class;
    }

    public function findByUserId(int $userId): ?Partner
    {
        return $this->query()->with('categoryCommissions')->where('user_id', $userId)->first();
    }

    public function countByStatus(PartnerStatus $status): int
    {
        return $this->query()->where('status', $status)->count();
    }

    public function adminQuery(?PartnerStatus $status = null): Builder
    {
        return $this->query()
            ->select('partners.*')
            ->with('user:id,name,email')
            ->withCount('bookings')
            ->withSum(['bookings as sales_total' => fn (Builder $query) => $query->whereNotIn('status', BookingStatus::voided())], 'total')
            ->when($status, fn (Builder $query, PartnerStatus $status) => $query->where('status', $status));
    }

    public function forCommissionMatrix(): Collection
    {
        return $this->query()
            ->with('categoryCommissions')
            ->where('status', '!=', PartnerStatus::Suspended)
            ->orderBy('company')
            ->get();
    }

    public function options(): array
    {
        return $this->query()->orderBy('company')->pluck('company', 'id')->all();
    }
}
