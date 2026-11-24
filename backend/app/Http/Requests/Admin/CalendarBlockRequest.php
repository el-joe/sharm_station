<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoomAvailabilityStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Block (closed) or unblock (open) a range of nights of one room from the occupancy grid.
 */
class CalendarBlockRequest extends FormRequest
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
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'from' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.now()->addYears(2)->toDateString()],
            'status' => ['required', Rule::in([RoomAvailabilityStatus::Open->value, RoomAvailabilityStatus::Closed->value])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['from.after_or_equal' => 'Past nights cannot be changed.'];
    }

    /**
     * @return list<string>
     */
    public function dates(): array
    {
        $period = CarbonPeriod::create(CarbonImmutable::parse($this->validated('from')), CarbonImmutable::parse($this->validated('to')));

        return array_values(array_map(fn ($date): string => $date->toDateString(), iterator_to_array($period)));
    }

    public function status(): RoomAvailabilityStatus
    {
        return RoomAvailabilityStatus::from($this->validated('status'));
    }
}
