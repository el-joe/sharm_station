<?php

namespace App\Http\Requests\Api;

use App\Enums\Currency;
use App\Http\Middleware\SetLocaleAndCurrency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper($this->input('currency'))]);
        }

        if (is_string($this->input('nationality_code'))) {
            $this->merge(['nationality_code' => strtoupper($this->input('nationality_code'))]);
        }
    }

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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'locale' => ['sometimes', 'required', Rule::in(SetLocaleAndCurrency::LOCALES)],
            'currency' => ['sometimes', 'required', Rule::enum(Currency::class)],
            'newsletter' => ['sometimes', 'boolean'],
            'nationality_code' => ['sometimes', 'nullable', 'string', 'size:2', Rule::exists('countries', 'code')],
        ];
    }
}
