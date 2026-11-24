<?php

namespace App\Http\Requests\Admin;

use App\Enums\ActivityAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivityFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-users');
    }

    protected function prepareForValidation(): void
    {
        $search = $this->input('search');
        $this->merge(['search' => is_array($search) ? ($search['value'] ?? null) : $search]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'causer_id' => ['nullable', 'integer'],
            'action' => ['nullable', Rule::enum(ActivityAction::class)],
            'subject_type' => ['nullable', 'string', 'max:60'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
