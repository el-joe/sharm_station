<?php

namespace App\Http\Requests\Api;

use App\Http\Middleware\SetLocaleAndCurrency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:40'],
            'locale' => ['nullable', Rule::in(SetLocaleAndCurrency::LOCALES)],
            'currency' => ['nullable', Rule::exists('currencies', 'code')->where('is_enabled', true)],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
