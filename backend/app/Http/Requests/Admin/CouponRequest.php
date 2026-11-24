<?php

namespace App\Http\Requests\Admin;

use App\Enums\CouponDiscountType;
use App\Enums\ServiceType;
use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-prices');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'partners_allowed' => $this->boolean('partners_allowed'),
            'is_active' => $this->boolean('is_active'),
            'services' => $this->input('services', []),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $coupon = $this->route('coupon');

        return [
            'code' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($coupon instanceof Coupon ? $coupon->id : null)],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', Rule::enum(CouponDiscountType::class)],
            'value' => ['required', 'numeric', 'gt:0', Rule::when($this->input('discount_type') === CouponDiscountType::Percent->value, ['max:100'], ['max:100000'])],
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
            'valid_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'minimum_total' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'services' => ['array'],
            'services.*' => [Rule::enum(ServiceType::class)],
            'partners_allowed' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Use letters, numbers, dashes and underscores only.',
            'code.unique' => 'Another coupon already uses this code.',
            'value.max' => 'A percentage cannot be more than 100.',
        ];
    }
}
