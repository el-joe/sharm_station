<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TransferFixedRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-prices');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_enabled' => $this->boolean('is_enabled')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transfer_hub_id' => ['required', 'integer', 'exists:transfer_hubs,id'],
            'vehicle_type_id' => ['nullable', 'integer', 'exists:vehicle_types,id'],
            'destination_name' => ['required', 'array'],
            'destination_name.en' => ['required', 'string', 'max:160'],
            'destination_name.it' => ['nullable', 'string', 'max:160'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'match_radius_km' => ['required', 'numeric', 'min:0.1', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
            'partner_price' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'is_enabled' => ['boolean'],
        ];
    }
}
