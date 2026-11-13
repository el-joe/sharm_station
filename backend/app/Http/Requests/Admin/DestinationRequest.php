<?php

namespace App\Http\Requests\Admin;

use App\Models\Destination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DestinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = (array) $this->input('name', []);
        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: ($name['en'] ?? ''))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $destination = $this->route('destination');

        return [
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:120'],
            'name.it' => ['nullable', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:140', Rule::unique('destinations', 'slug')->ignore($destination instanceof Destination ? $destination->id : null)],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name.en' => 'name (English)'];
    }
}
