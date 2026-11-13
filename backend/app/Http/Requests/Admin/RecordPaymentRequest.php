<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Record balance payment" on the booking detail (SRS 10.1). Amount in EUR.
 */
class RecordPaymentRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'paid_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'type' => ['nullable', Rule::in([PaymentType::Deposit->value, PaymentType::Balance->value])],
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
