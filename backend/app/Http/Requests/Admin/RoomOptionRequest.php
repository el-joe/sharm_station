<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoomOptionUnitType;
use App\Enums\RoomPricePeriod;
use App\Enums\RoomPricingMode;
use App\Models\Package;
use App\Models\RoomOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Optional room of a multi-day trip, Nile trip or package stage (SRS 7.1). Packages need the stage.
 */
class RoomOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $owner = $this->route('roomOption') instanceof RoomOption ? $this->route('roomOption')->owner : ($this->route('package') ?? $this->route('trip'));
        $stages = $owner instanceof Package ? $owner->stages()->pluck('position')->all() : [];

        return [
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:120'],
            'name.it' => ['nullable', 'string', 'max:120'],
            'unit_type' => ['required', Rule::enum(RoomOptionUnitType::class)],
            'stars' => ['nullable', 'integer', 'min:1', 'max:5'],
            'pricing_mode' => ['required', Rule::enum(RoomPricingMode::class)],
            'price_period' => ['required', Rule::enum(RoomPricePeriod::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'max_adults' => ['required', 'integer', 'min:1', 'max:20'],
            'max_children' => ['required', 'integer', 'min:0', 'max:20'],
            'max_total' => ['required', 'integer', 'min:1', 'max:30', 'gte:max_adults'],
            'max_quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'stage_position' => $owner instanceof Package ? ['required', 'integer', Rule::in($stages)] : ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'max_total.gte' => 'The total capacity must be at least the maximum adults.',
            'stage_position.in' => 'Choose one of the package stages.',
        ];
    }
}
