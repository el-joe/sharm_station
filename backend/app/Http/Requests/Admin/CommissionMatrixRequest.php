<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CommissionMatrixRequest extends FormRequest
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
        return [
            'partners' => ['required', 'array'],
            'partners.*.default' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'partners.*.categories' => ['nullable', 'array'],
            'partners.*.categories.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'partners.*.default.max' => 'A commission cannot exceed 100%.',
            'partners.*.categories.*.max' => 'A commission cannot exceed 100%.',
            'partners.*.categories.*.numeric' => 'Commissions must be numbers.',
        ];
    }
}
