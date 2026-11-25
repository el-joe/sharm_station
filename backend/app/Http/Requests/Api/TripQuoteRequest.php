<?php

namespace App\Http\Requests\Api;

use App\Enums\BookingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TripQuoteRequest extends FormRequest
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
     * Shared with the booking request.
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
            'units' => ['nullable', 'array', 'max:20'],
            'units.*.unit_option_id' => ['required', 'integer'],
            'units.*.quantity' => ['required', 'integer', 'min:0', 'max:100'],
            'addons' => ['nullable', 'array', 'max:30'],
            'addons.*.addon_id' => ['required', 'integer'],
            'addons.*.quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            ...self::roomOptionRules(),
        ];
    }

    /**
     * Optional rooms of multi-day trips, Nile trips and packages (SRS 7.1), each with its guests.
     *
     * @return array<string, mixed>
     */
    public static function roomOptionRules(): array
    {
        return [
            'room_options' => ['nullable', 'array', 'max:30'],
            'room_options.*.room_option_id' => ['required', 'integer'],
            'room_options.*.adults' => ['required', 'integer', 'min:0', 'max:20'],
            'room_options.*.children_ages' => ['nullable', 'array', 'max:20'],
            'room_options.*.children_ages.*' => ['integer', 'min:0', 'max:17'],
        ];
    }
}
