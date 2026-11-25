<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Api\RoomQuoteRequest;
use App\Http\Requests\Api\TripQuoteRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin change of a booking: dates, rooms, and for trips and packages the party, units and add-ons
 * (preview and save use the same payload).
 */
class BookingChangeRequest extends FormRequest
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
        $stay = RoomQuoteRequest::stayRules();

        return [
            'check_in' => ['nullable', 'date_format:Y-m-d'],
            'check_out' => ['nullable', 'required_with:check_in', 'date_format:Y-m-d', 'after:check_in'],
            'travel_date' => ['nullable', 'date_format:Y-m-d'],
            'rooms' => ['nullable', 'array', 'min:1', 'max:10'],
            'rooms.*.room_id' => $stay['rooms.*.room_id'],
            'rooms.*.adults' => $stay['rooms.*.adults'],
            'rooms.*.children_ages' => $stay['rooms.*.children_ages'],
            'rooms.*.children_ages.*' => $stay['rooms.*.children_ages.*'],
            'rooms.*.extra_beds' => $stay['rooms.*.extra_beds'],
            'rooms.*.cots' => $stay['rooms.*.cots'],
            ...TripQuoteRequest::roomOptionRules(),
            'adults' => ['nullable', 'integer', 'min:1', 'max:100'],
            'child_ages' => ['nullable', 'array', 'max:50'],
            'child_ages.*' => ['integer', 'min:0', 'max:17'],
            'units' => ['nullable', 'array', 'max:20'],
            'units.*.unit_option_id' => ['required', 'integer'],
            'units.*.quantity' => ['required', 'integer', 'min:0', 'max:100'],
            'addons' => ['nullable', 'array', 'max:30'],
            'addons.*.addon_id' => ['required', 'integer', 'distinct'],
            'addons.*.quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return RoomQuoteRequest::stayMessages();
    }
}
