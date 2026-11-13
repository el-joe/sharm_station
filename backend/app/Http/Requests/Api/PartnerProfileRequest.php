<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PartnerProfileRequest extends FormRequest
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
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:64'],
            'notify_bookings' => ['sometimes', 'boolean'],
            'notify_statements' => ['sometimes', 'boolean'],
        ];
    }
}
