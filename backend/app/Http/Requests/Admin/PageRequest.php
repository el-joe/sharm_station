<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PageRequest extends FormRequest
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
            'title.en' => ['required', 'string', 'max:160'],
            'title.it' => ['nullable', 'string', 'max:160'],
            'intro' => ['nullable', 'array'],
            'intro.*' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'array'],
            'body.*' => ['nullable', 'string', 'max:200000'],
            'seo' => ['nullable', 'array'],
            'seo.*.title' => ['nullable', 'string', 'max:70'],
            'seo.*.description' => ['nullable', 'string', 'max:170'],
        ];
    }
}
