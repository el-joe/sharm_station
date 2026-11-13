<?php

namespace App\Http\Requests\Admin;

use App\Enums\BookingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(BookingStatus::class)],
            'note' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn (): bool => in_array($this->input('status'), [BookingStatus::Cancelled->value, BookingStatus::Refunded->value], true))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['note.required' => 'Add a note explaining the cancellation or refund.'];
    }

    public function status(): BookingStatus
    {
        return BookingStatus::from($this->validated('status'));
    }
}
