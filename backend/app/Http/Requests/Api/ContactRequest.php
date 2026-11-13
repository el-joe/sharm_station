<?php

namespace App\Http\Requests\Api;

use App\Enums\ContactSubject;
use App\Http\Middleware\SetLocaleAndCurrency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['required', Rule::enum(ContactSubject::class)],
            'booking_reference' => ['nullable', 'string', 'max:20'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'locale' => ['nullable', Rule::in(SetLocaleAndCurrency::LOCALES)],
        ];
    }
}
