<?php

namespace App\Http\Requests\Admin;

use App\Enums\VehicleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-prices');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_enabled' => $this->boolean('is_enabled')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:80'],
            'name.it' => ['nullable', 'string', 'max:80'],
            'category' => ['required', Rule::enum(VehicleType::class)],
            'capacity' => ['required', 'integer', 'min:1', 'max:80'],
            'luggage' => ['required', 'integer', 'min:0', 'max:80'],
            'multiplier' => ['required', 'numeric', 'min:0.1', 'max:10'],
            'min_fare' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'string', 'max:255'],
            'is_enabled' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
