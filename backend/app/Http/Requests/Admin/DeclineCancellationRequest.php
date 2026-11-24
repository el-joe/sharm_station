<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DeclineCancellationRequest extends FormRequest
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
        return ['decline_reason' => ['required', 'string', 'min:3', 'max:500']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['decline_reason.required' => 'Explain why the request is declined: the customer will read it.'];
    }
}
