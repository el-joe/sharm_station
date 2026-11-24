<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Coupon\CouponCheckData;
use App\Http\Requests\Api\ValidateCouponRequest;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;

class CouponController extends ApiController
{
    public function __construct(private readonly CouponService $coupons) {}

    /**
     * Checks a code for the checkout without using it: {valid, code, discount_type, value, discount_eur, message}.
     * The booking applies the code again on the server.
     */
    public function check(ValidateCouponRequest $request): JsonResponse
    {
        $partner = $request->boolean('for_partner') ? $request->user('sanctum')?->activePartner() : null;

        return response()->json($this->coupons->check(CouponCheckData::fromRequest($request, $partner))->toArray());
    }
}
