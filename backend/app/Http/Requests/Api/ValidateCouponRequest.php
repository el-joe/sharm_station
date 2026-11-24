<?php

namespace App\Http\Requests\Api;

use App\Enums\ServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Checkout coupon check: {code, service_type, total_eur}; "email" (the lead traveller) helps the per-customer limit,
 * "for_partner" checks the code as a partner booking of the signed-in partner.
 */
class ValidateCouponRequest extends FormRequest
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
        return [
            'code' => ['required', 'string', 'max:40'],
            'service_type' => ['required', Rule::enum(ServiceType::class)],
            'total_eur' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'email' => ['nullable', 'email', 'max:255'],
            'for_partner' => ['nullable', 'boolean'],
        ];
    }
}
