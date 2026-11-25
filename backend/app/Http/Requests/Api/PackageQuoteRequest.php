<?php

namespace App\Http\Requests\Api;

use App\Enums\BookingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PackageQuoteRequest extends FormRequest
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
        return self::quoteRules();
    }

    /**
     * Shared with the booking request. Packages are always per person (no units).
     *
     * @return array<string, mixed>
     */
    public static function quoteRules(): array
    {
        return [
            'booking_type' => ['required', Rule::enum(BookingType::class)],
            'travel_date' => ['required', 'date_format:Y-m-d'],
            'adults' => ['required', 'integer', 'min:1', 'max:100'],
            'children' => ['nullable', 'integer', 'min:0', 'max:50'],
            'child_ages' => ['nullable', 'array', 'max:50'],
            'child_ages.*' => ['integer', 'min:0', 'max:17'],
            'addons' => ['nullable', 'array', 'max:30'],
            'addons.*.addon_id' => ['required', 'integer'],
            'addons.*.quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            ...TripQuoteRequest::roomOptionRules(),
        ];
    }
}
