<?php

namespace App\Http\Requests\Admin;

use App\Enums\Currency;
use App\Enums\PaymentMethodType;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-prices');
    }

    protected function prepareForValidation(): void
    {
        $name = (array) $this->input('name', []);

        $this->merge([
            'code' => Str::slug((string) ($this->input('code') ?: ($name['en'] ?? '')), '_'),
            'proof_required' => $this->boolean('proof_required'),
            'is_enabled' => $this->boolean('is_enabled'),
            'currencies' => array_values(array_filter((array) $this->input('currencies', []))),
            'account_details' => array_values((array) $this->input('account_details', [])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var PaymentMethod|null $method */
        $method = $this->route('paymentMethod');

        return [
            'code' => ['required', 'string', 'max:40', Rule::unique('payment_methods', 'code')->ignore($method?->id)],
            'type' => ['required', Rule::enum(PaymentMethodType::class)],
            'driver' => ['nullable', 'required_if:type,online', Rule::in(array_keys((array) config('payments.gateways')))],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:80'],
            'name.it' => ['nullable', 'string', 'max:80'],
            'instructions' => ['nullable', 'array'],
            'instructions.*' => ['nullable', 'string', 'max:2000'],
            'account_details' => ['array', 'max:12'],
            'account_details.*.label' => ['nullable', 'string', 'max:60'],
            'account_details.*.value' => ['nullable', 'string', 'max:200'],
            'currencies' => ['array'],
            'currencies.*' => [Rule::enum(Currency::class)],
            'proof_required' => ['boolean'],
            'surcharge_percent' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'deadline_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'mode' => ['nullable', Rule::in(['test', 'live'])],
            'is_enabled' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
