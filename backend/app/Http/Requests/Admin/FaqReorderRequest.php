<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FaqReorderRequest extends FormRequest
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
            'categories' => ['required', 'array', 'max:200'],
            'categories.*' => ['integer'],
            'items' => ['nullable', 'array', 'max:200'],
            'items.*' => ['array', 'max:500'],
            'items.*.*' => ['integer'],
        ];
    }

    /**
     * @return list<int>
     */
    public function categoryIds(): array
    {
        return array_map('intval', $this->validated('categories'));
    }

    /**
     * @return array<int, list<int>>
     */
    public function itemIds(): array
    {
        return collect($this->validated('items', []))->map(fn (array $ids): array => array_map('intval', array_values($ids)))->all();
    }
}
