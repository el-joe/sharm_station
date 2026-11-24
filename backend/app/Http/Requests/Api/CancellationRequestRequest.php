<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cancellation request on a booking: the reason, plus the booking token when the caller is not signed in.
 */
class CancellationRequestRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'token' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['reason.required' => 'Tell us why you want to cancel.'];
    }

    public function accessToken(): ?string
    {
        return $this->validated('token') ?? $this->query('token');
    }
}
