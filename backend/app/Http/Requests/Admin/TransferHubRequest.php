<?php

namespace App\Http\Requests\Admin;

use App\Enums\HubType;
use App\Models\TransferHub;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TransferHubRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-prices');
    }

    protected function prepareForValidation(): void
    {
        $name = (array) $this->input('name', []);

        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: ($name['en'] ?? ''))),
            'is_enabled' => $this->boolean('is_enabled'),
            'distance_tiers' => array_values((array) $this->input('distance_tiers', [])),
            'extras' => array_values((array) $this->input('extras', [])),
            'vehicle_type_ids' => array_values(array_filter((array) $this->input('vehicle_type_ids', []))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var TransferHub|null $hub */
        $hub = $this->route('hub');

        return [
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:120'],
            'name.it' => ['nullable', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:140', Rule::unique('transfer_hubs', 'slug')->ignore($hub?->id)],
            'type' => ['required', Rule::enum(HubType::class)],
            'destination_id' => ['nullable', 'integer', 'exists:destinations,id'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['required', 'numeric', 'min:1', 'max:1000'],
            'max_distance_km' => ['nullable', 'numeric', 'min:1', 'max:2000'],
            'base_fare' => ['required', 'numeric', 'min:0'],
            'min_fare' => ['required', 'numeric', 'min:0'],
            'distance_tiers' => ['required', 'array', 'min:1', 'max:10'],
            'distance_tiers.*.up_to_km' => ['nullable', 'numeric', 'min:0.1'],
            'distance_tiers.*.rate' => ['required', 'numeric', 'min:0'],
            'waiting_grace_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'waiting_rate_per_hour' => ['nullable', 'numeric', 'min:0'],
            'night_surcharge' => ['nullable', 'numeric', 'min:0'],
            'fixed_fees' => ['nullable', 'numeric', 'min:0'],
            'extras' => ['nullable', 'array', 'max:20'],
            'extras.*.code' => ['required', 'string', 'max:40', 'alpha_dash'],
            'extras.*.label' => ['required', 'string', 'max:80'],
            'extras.*.price' => ['nullable', 'numeric', 'min:0'],
            'return_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'deposit_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_notice_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'vehicle_type_ids' => ['array'],
            'vehicle_type_ids.*' => ['integer', 'exists:vehicle_types,id'],
            'is_enabled' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
