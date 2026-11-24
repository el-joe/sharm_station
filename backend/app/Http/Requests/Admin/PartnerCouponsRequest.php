<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Partner profile → "Coupons allowed" toggle (whether the partner may apply coupon codes to its bookings).
 */
class PartnerCouponsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['coupons_allowed' => ['required', 'boolean']];
    }
}
