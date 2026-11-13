<?php

namespace App\Http\Requests\Api;

use App\Http\Middleware\SetLocaleAndCurrency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NewsletterRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'locale' => ['nullable', Rule::in(SetLocaleAndCurrency::LOCALES)],
            'source' => ['nullable', 'string', 'max:40'],
        ];
    }
}
