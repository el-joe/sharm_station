<?php

namespace App\Http\Requests\Admin;

use App\Enums\NotificationEvent;
use App\Enums\NotificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationLogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-settings');
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
            'event' => ['nullable', Rule::enum(NotificationEvent::class)],
            'status' => ['nullable', Rule::enum(NotificationStatus::class)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
