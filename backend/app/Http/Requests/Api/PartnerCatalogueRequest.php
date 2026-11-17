<?php

namespace App\Http\Requests\Api;

use App\Enums\CatalogueService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Partner catalogue / price list filters. Unknown nationalities price with the default group.
 */
class PartnerCatalogueRequest extends FormRequest
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
            'service' => ['nullable', Rule::enum(CatalogueService::class)],
            'nationality' => ['nullable', 'string', 'size:2'],
            'destination' => ['nullable', 'string', 'max:255'],
            'q' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
