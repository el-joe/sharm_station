<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Package listing filters (no date filter, CR-5).
 */
class PackageIndexRequest extends FormRequest
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
            'destination' => ['nullable', 'string', 'max:120'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0', Rule::when($this->filled('price_min'), 'gte:price_min')],
            'days_min' => ['nullable', 'integer', 'min:1', 'max:60'],
            'days_max' => ['nullable', 'integer', 'min:1', 'max:60'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['recommended', 'price_asc', 'price_desc', 'duration', 'newest', 'rating', 'most_booked'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
