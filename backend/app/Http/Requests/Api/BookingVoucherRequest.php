<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /bookings/{reference}/voucher.pdf?token=&prices=0|1&locale=en|it
 */
class BookingVoucherRequest extends FormRequest
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
            'token' => ['nullable', 'string', 'max:100'],
            'prices' => ['nullable', 'in:0,1'],
            'locale' => ['nullable', 'in:en,it'],
        ];
    }

    public function withPrices(): bool
    {
        return $this->validated('prices', '1') === '1';
    }
}
