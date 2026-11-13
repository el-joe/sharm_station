<?php

namespace App\Http\Requests\Api;

use App\Enums\BookingType;
use App\Enums\PricingMode;
use App\Enums\TripType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Trip listing filters. There is no date filter (CR-5).
 */
class TripIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('type'))) {
            $this->merge(['type' => array_values(array_filter(explode(',', $this->input('type'))))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', 'array'],
            'type.*' => [Rule::enum(TripType::class)],
            'destination' => ['nullable', 'string', 'max:120'],
            'booking_type' => ['nullable', Rule::enum(BookingType::class)],
            'pricing_mode' => ['nullable', Rule::enum(PricingMode::class)],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0', Rule::when($this->filled('price_min'), 'gte:price_min')],
            'free_cancellation' => ['nullable', 'boolean'],
            'category' => ['nullable', 'string', 'max:40'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['recommended', 'price_asc', 'price_desc', 'newest', 'duration', 'rating', 'most_booked'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
