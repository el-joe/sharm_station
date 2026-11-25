<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CalendarMoveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['dry_run' => $this->boolean('dry_run')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'booking_id' => ['required', 'integer'],
            'from_room_id' => ['required', 'integer'],
            'to_room_id' => ['required', 'integer'],
            'check_in' => ['required', 'date_format:Y-m-d'],
            'dry_run' => ['boolean'],
        ];
    }
}
