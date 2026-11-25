<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Balance due date set by the admin on a booking (SRS 10.1); empty = the default from the settings.
 */
class BalanceDueRequest extends FormRequest
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
        return ['balance_due_on' => ['nullable', 'date_format:Y-m-d']];
    }
}
