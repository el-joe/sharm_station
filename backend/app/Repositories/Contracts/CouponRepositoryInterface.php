<?php

namespace App\Repositories\Contracts;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepositoryInterface<Coupon>
 */
interface CouponRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return Builder<Coupon>
     */
    public function adminQuery(?bool $active = null): Builder;

    public function findByCode(string $code, bool $lock = false): ?Coupon;

    public function redemptionsCount(Coupon $coupon): int;

    public function redemptionsCountFor(Coupon $coupon, ?int $userId, ?string $email): int;
}
