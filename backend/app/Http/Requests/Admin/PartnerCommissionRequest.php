<?php

namespace App\Http\Requests\Admin;

use App\Enums\CommissionCategory;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Partner commission overrides (partner profile → Commissions). Empty = standard (global) rate.
 */
class PartnerCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-prices');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['commissions' => ['nullable', 'array']];

        foreach (CommissionCategory::cases() as $category) {
            $rules["commissions.{$category->value}"] = ['nullable', 'numeric', 'min:0', 'max:100'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'commissions.*.max' => 'A commission cannot exceed 100%.',
            'commissions.*.min' => 'A commission cannot be negative.',
            'commissions.*.numeric' => 'Commissions must be numbers.',
        ];
    }
}
