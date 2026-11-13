<?php

namespace App\Http\Requests\Admin;

use App\DTOs\Currency\CurrencyRateData;
use Illuminate\Foundation\Http\FormRequest;

class CurrencyRatesRequest extends FormRequest
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
            'currencies' => ['required', 'array'],
            'currencies.*.rate_to_eur' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'currencies.*.margin_percent' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'currencies.*.is_enabled' => ['boolean'],
            'currencies.*.is_manual_rate' => ['boolean'],
        ];
    }

    /**
     * @return list<CurrencyRateData>
     */
    public function rows(): array
    {
        $rows = [];

        foreach ($this->validated('currencies') as $id => $row) {
            $rows[] = CurrencyRateData::fromArray((int) $id, $row);
        }

        return $rows;
    }
}
