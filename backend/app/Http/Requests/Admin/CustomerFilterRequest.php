<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CustomerFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $search = $this->input('search');
        $this->merge(['search' => is_array($search) ? ($search['value'] ?? null) : $search]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'nationality_code' => ['nullable', 'string', 'size:2'],
            'active' => ['nullable', 'in:0,1'],
            'with_bookings' => ['nullable', 'in:0,1'],
        ];
    }
}
