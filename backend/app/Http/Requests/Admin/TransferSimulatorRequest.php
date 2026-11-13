<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Api\TransferQuoteRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pricing simulator (SRS 5.2): the same fields as the public quote.
 */
class TransferSimulatorRequest extends FormRequest
{
    /**
     * Invalid input goes back to the empty simulator (never to the same GET URL).
     *
     * @var string
     */
    protected $redirectRoute = 'admin.transfers.simulator';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'round_trip' => $this->boolean('round_trip'),
            'return_at' => $this->boolean('round_trip') ? $this->input('return_at') : null,
            'extras' => array_values(array_filter((array) $this->input('extras', []))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->filled('hub_id') ? TransferQuoteRequest::quoteRules() : [];
    }
}
