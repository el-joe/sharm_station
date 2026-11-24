<?php

namespace App\Services;

use App\DTOs\Coupon\CouponCheckData;
use App\DTOs\Coupon\CouponData;
use App\DTOs\Coupon\CouponResultData;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Repositories\Contracts\CouponRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

/**
 * Coupon codes (SRS 14.1): validation against service, dates, limits and minimum total, and redemption on bookings.
 * Partner bookings only accept codes that allow partners, and only for partners with coupon permission (SRS 8).
 */
class CouponService
{
    public function __construct(private readonly CouponRepositoryInterface $coupons) {}

    /**
     * Checks a code without using it. Pass $lock inside the booking transaction so limits cannot be exceeded concurrently.
     */
    public function check(CouponCheckData $check, bool $lock = false): CouponResultData
    {
        $code = $check->code;
        $coupon = $code === '' ? null : $this->coupons->findByCode($code, $lock);

        if ($coupon === null) {
            return CouponResultData::invalid($code, 'This code does not exist.');
        }

        $today = now()->startOfDay();
        $email = $check->email !== null ? strtolower($check->email) : null;

        $error = match (true) {
            ! $coupon->is_active => 'This code is no longer active.',
            $coupon->valid_from !== null && $coupon->valid_from->gt($today) => 'This code is valid from '.$coupon->valid_from->format('j M Y').'.',
            $coupon->valid_to !== null && $coupon->valid_to->lt($today) => 'This code has expired.',
            $check->partner !== null && ! ($coupon->partners_allowed && $check->partner->coupons_allowed) => 'Coupon codes cannot be used on partner bookings.',
            ! $coupon->appliesTo($check->serviceType) => 'This code does not apply to '.strtolower($check->serviceType->label()).' bookings.',
            $coupon->minimum_total !== null && $check->totalEur < (float) $coupon->minimum_total => 'This code needs a minimum total of '.Number::currency((float) $coupon->minimum_total, 'EUR', 'en').'.',
            $coupon->usage_limit !== null && $this->coupons->redemptionsCount($coupon) >= $coupon->usage_limit => 'This code has reached its usage limit.',
            $coupon->usage_limit_per_customer !== null && $this->coupons->redemptionsCountFor($coupon, $check->userId, $email) >= $coupon->usage_limit_per_customer => 'You have already used this code.',
            default => null,
        };

        if ($error !== null) {
            return CouponResultData::invalid($code, $error, $coupon);
        }

        $discount = $coupon->discountFor($check->totalEur);

        return new CouponResultData(true, $coupon->code, 'Code applied: −'.Number::currency($discount, 'EUR', 'en').'.', $coupon, $discount);
    }

    /**
     * Records the use of a coupon on a booking (one redemption per booking).
     */
    public function redeem(Coupon $coupon, Booking $booking, float $discount): CouponRedemption
    {
        return $coupon->redemptions()->create([
            'booking_id' => $booking->id,
            'user_id' => $booking->partner_id === null ? $booking->user_id : null,
            'partner_id' => $booking->partner_id,
            'email' => strtolower($booking->lead_email),
            'discount_amount' => $discount,
        ]);
    }

    /**
     * @return Builder<Coupon>
     */
    public function adminQuery(?bool $active = null): Builder
    {
        return $this->coupons->adminQuery($active);
    }

    public function create(CouponData $data): Coupon
    {
        return $this->coupons->create($data->toAttributes());
    }

    public function update(Coupon $coupon, CouponData $data): Coupon
    {
        return $this->coupons->update($coupon, $data->toAttributes());
    }

    /**
     * Bookings keep the code and the discount; redemptions are removed with the coupon.
     */
    public function delete(Coupon $coupon): void
    {
        $this->coupons->delete($coupon);
    }

    public function usageCount(Coupon $coupon): int
    {
        return $this->coupons->redemptionsCount($coupon);
    }
}
