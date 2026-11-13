<?php

namespace App\Http\Requests\Admin;

use App\Enums\BuildingType;
use App\Enums\PublishStatus;
use App\Models\Building;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Building editor (SRS 6.1). Taxes included (CR-2) disables the tax per night.
 */
class BuildingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = (array) $this->input('name', []);
        $building = $this->route('building');

        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: ($name['en'] ?? ''))),
            'taxes_included' => $this->boolean('taxes_included'),
            'featured' => $this->boolean('featured'),
            'amenities' => $this->input('amenities', []),
            'google_place_id' => filled($this->input('google_place_id')) ? trim((string) $this->input('google_place_id')) : null,
        ]);

        // Editors cannot change money fields: keep the stored values.
        if (! $this->user()?->can('manage-prices')) {
            $this->merge([
                'deposit_percent' => $building instanceof Building ? $building->deposit_percent : 30,
                'taxes_included' => $building instanceof Building ? $building->taxes_included : true,
                'tax_per_night' => $building instanceof Building ? $building->tax_per_night : null,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $building = $this->route('building');

        return [
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:160'],
            'name.it' => ['nullable', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:180', Rule::unique('buildings', 'slug')->ignore($building instanceof Building ? $building->id : null)],
            'type' => ['required', Rule::enum(BuildingType::class)],
            'destination_id' => ['required', 'integer', 'exists:destinations,id'],
            'status' => ['required', Rule::enum(PublishStatus::class)],
            'featured' => ['boolean'],
            'address' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'stars' => ['nullable', 'integer', 'between:1,5'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:20000'],
            'amenities' => ['array'],
            'amenities.*' => ['string', 'max:60'],
            'check_in_from' => ['nullable', 'date_format:H:i'],
            'check_out_until' => ['nullable', 'date_format:H:i'],
            'deposit_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'cancellation_policy' => ['nullable', 'array'],
            'cancellation_policy.*' => ['nullable', 'string', 'max:5000'],
            'taxes_included' => ['boolean'],
            'tax_per_night' => ['nullable', 'numeric', 'min:0', 'max:10000', Rule::requiredIf(fn (): bool => ! $this->boolean('taxes_included'))],
            'google_place_id' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-:]+$/'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tax_per_night.required' => 'Enter the tax per night, or switch "Taxes included" on.',
            'google_place_id.regex' => 'A Google Place ID only contains letters, numbers, "-", "_" and ":".',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();
        $data['cancellation_policy'] = array_filter($data['cancellation_policy'] ?? [], fn ($value): bool => filled($value)) ?: null;

        return $data;
    }
}
