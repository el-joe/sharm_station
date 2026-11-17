<?php

namespace App\Http\Requests\Api;

use App\Enums\TravelerType;
use Illuminate\Validation\Rule;

/**
 * Partner panel booking (CR-15): the quote payload plus the lead client, travellers and payment method.
 * The partner is the booker; the client's nationality sets the price.
 */
class PartnerBookingRequest extends PartnerQuoteRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isTransfer = $this->input('product_type') === 'transfer';

        return [
            ...parent::rules(),
            'lead' => ['required', 'array'],
            'lead.name' => ['required', 'string', 'max:255'],
            'lead.email' => ['required', 'email', 'max:255'],
            'lead.phone' => [$isTransfer ? 'required' : 'nullable', 'string', 'max:40'],
            'travelers' => [$isTransfer ? 'nullable' : 'required', 'array', 'min:1', 'max:100'],
            'travelers.*.first_name' => ['required', 'string', 'max:100'],
            'travelers.*.last_name' => ['required', 'string', 'max:100'],
            'travelers.*.type' => ['required', Rule::enum(TravelerType::class)],
            'travelers.*.age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'travelers.*.nationality' => ['nullable', 'string', 'size:2'],
            'travelers.*.passport_number' => ['nullable', 'string', 'max:40'],
            'payment_method' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
