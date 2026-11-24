<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\CouponDataTable;
use App\DTOs\Coupon\CouponData;
use App\Enums\CouponDiscountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CouponRequest;
use App\Models\Coupon;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Coupon codes (SRS 14.1): percentage or fixed EUR, validity, usage limits and services.
 */
class CouponController extends Controller
{
    public function __construct(private readonly CouponService $coupons) {}

    public function index(): View
    {
        return view('admin.coupons.index');
    }

    public function data(Request $request, CouponDataTable $table): JsonResponse
    {
        $active = $request->query('active');

        return $table->json($active === null || $active === '' ? null : filter_var($active, FILTER_VALIDATE_BOOLEAN));
    }

    public function create(): View
    {
        return view('admin.coupons.form', [
            'coupon' => new Coupon(['is_active' => true, 'discount_type' => CouponDiscountType::Percent, 'usage_limit_per_customer' => 1]),
            'usage' => 0,
        ]);
    }

    public function store(CouponRequest $request): RedirectResponse
    {
        $coupon = $this->coupons->create(CouponData::fromRequest($request));

        return redirect()->route('admin.coupons.index')->with('success', "Coupon {$coupon->code} created.");
    }

    public function edit(Coupon $coupon): View
    {
        return view('admin.coupons.form', ['coupon' => $coupon, 'usage' => $this->coupons->usageCount($coupon)]);
    }

    public function update(CouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $this->coupons->update($coupon, CouponData::fromRequest($request));

        return redirect()->route('admin.coupons.index')->with('success', "Coupon {$coupon->code} saved.");
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $this->coupons->delete($coupon);

        return redirect()->route('admin.coupons.index')->with('success', "Coupon {$coupon->code} deleted.");
    }
}
