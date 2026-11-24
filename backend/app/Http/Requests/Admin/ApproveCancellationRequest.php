<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ApproveCancellationRequest extends FormRequest
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
            'refund_amount' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
