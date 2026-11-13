<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class PartnerApplyRequest extends FormRequest
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
        $guest = $this->user('sanctum') === null;

        return [
            'company' => ['required', 'string', 'max:255'],
            'name' => [$guest ? 'required' : 'nullable', 'string', 'max:255'],
            'email' => [$guest ? 'required' : 'nullable', 'email', 'max:255'],
            'password' => [$guest ? 'required' : 'nullable', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'size:2'],
            'website' => ['nullable', 'url', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:64'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
