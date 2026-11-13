<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoomAvailabilityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoomAvailabilityRequest extends FormRequest
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
            'dates' => ['required', 'array', 'min:1', 'max:366'],
            'dates.*' => ['date_format:Y-m-d', 'after_or_equal:today'],
            'status' => ['required', Rule::in([RoomAvailabilityStatus::Open->value, RoomAvailabilityStatus::Closed->value])],
        ];
    }

    /**
     * @return list<string>
     */
    public function dates(): array
    {
        return array_values(array_unique($this->validated('dates')));
    }

    public function status(): RoomAvailabilityStatus
    {
        return RoomAvailabilityStatus::from($this->validated('status'));
    }
}
