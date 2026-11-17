<?php

namespace App\Http\Requests\Admin;

use App\Enums\AddonPricingBasis;
use App\Enums\BookingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-prices');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'required' => $this->boolean('required'),
            'is_active' => $this->boolean('is_active'),
            'booking_types' => $this->input('booking_types', []),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:120'],
            'name.it' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'pricing_basis' => ['required', Rule::enum(AddonPricingBasis::class)],
            'required' => ['boolean'],
            'max_quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'booking_types' => ['array'],
            'booking_types.*' => [Rule::enum(BookingType::class)],
            'is_active' => ['boolean'],
        ];
    }
}
