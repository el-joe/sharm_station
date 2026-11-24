<?php

namespace App\Repositories\Eloquent;

use App\Models\Coupon;
use App\Repositories\Contracts\CouponRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<Coupon>
 */
class CouponRepository extends BaseRepository implements CouponRepositoryInterface
{
    protected function model(): string
    {
        return Coupon::class;
    }

    public function adminQuery(?bool $active = null): Builder
    {
        return $this->query()
            ->select('coupons.*')
            ->withCount('redemptions')
            ->withSum('redemptions', 'discount_amount')
            ->when($active !== null, fn (Builder $query) => $query->where('is_active', $active));
    }

    public function findByCode(string $code, bool $lock = false): ?Coupon
    {
        return $this->query()
            ->where('code', strtoupper(trim($code)))
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->first();
    }

    public function redemptionsCount(Coupon $coupon): int
    {
        return $coupon->redemptions()->count();
    }

    public function redemptionsCountFor(Coupon $coupon, ?int $userId, ?string $email): int
    {
        if ($userId === null && ($email === null || $email === '')) {
            return 0;
        }

        return $coupon->redemptions()
            ->where(function (Builder $query) use ($userId, $email): void {
                $query->when($userId !== null, fn (Builder $query) => $query->orWhere('user_id', $userId))
                    ->when($email !== null && $email !== '', fn (Builder $query) => $query->orWhere('email', strtolower((string) $email)));
            })
            ->count();
    }
}
