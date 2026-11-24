<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FaqCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-content');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:120'],
            'title.it' => ['nullable', 'string', 'max:120'],
            'key' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/'],
        ];
    }
}
