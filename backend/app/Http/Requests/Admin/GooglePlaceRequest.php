<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GooglePlaceRequest extends FormRequest
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
        return ['place_id' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-:]+$/']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['place_id.regex' => 'A Google Place ID only contains letters, numbers, "-", "_" and ":".'];
    }
}
