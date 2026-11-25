<?php

namespace App\Http\Requests\Api;

use App\Services\Pricing\RoomPricingEngine;
use Illuminate\Foundation\Http\FormRequest;

class RoomQuoteRequest extends FormRequest
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
            'room_id' => ['required_without:rooms', 'nullable', 'integer'],
            ...self::stayRules(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function stayRules(): array
    {
        return [
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'adults' => ['required_without:rooms', 'nullable', 'integer', 'min:1', 'max:40'],
            'children' => ['nullable', 'integer', 'min:0', 'max:40'],
            'child_ages' => ['nullable', 'array', 'max:40'],
            'child_ages.*' => ['integer', 'min:0', 'max:17'],
            'extra_beds' => ['nullable', 'integer', 'min:0', 'max:10'],
            'cots' => ['nullable', 'integer', 'min:0', 'max:10'],
            'rooms' => ['nullable', 'array', 'min:1', 'max:'.RoomPricingEngine::MAX_ROOMS],
            'rooms.*.room_id' => ['required', 'integer', 'distinct'],
            'rooms.*.adults' => ['required', 'integer', 'min:0', 'max:20'],
            'rooms.*.children_ages' => ['nullable', 'array', 'max:20'],
            'rooms.*.children_ages.*' => ['integer', 'min:0', 'max:17'],
            'rooms.*.extra_beds' => ['nullable', 'integer', 'min:0', 'max:10'],
            'rooms.*.cots' => ['nullable', 'integer', 'min:0', 'max:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::stayMessages();
    }

    /**
     * @return array<string, string>
     */
    public static function stayMessages(): array
    {
        return [
            'rooms.*.room_id.distinct' => __('allocation.shared_room', ['room' => __('allocation.this_room')]),
        ];
    }
}
