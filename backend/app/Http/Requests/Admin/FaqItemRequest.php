<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FaqItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-content');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'faq_category_id' => ['required', 'integer', 'exists:faq_categories,id'],
            'question' => ['required', 'array'],
            'question.en' => ['required', 'string', 'max:300'],
            'question.it' => ['nullable', 'string', 'max:300'],
            'answer' => ['required', 'array'],
            'answer.en' => ['required', 'string', 'max:20000'],
            'answer.it' => ['nullable', 'string', 'max:20000'],
            'is_active' => ['boolean'],
        ];
    }
}
