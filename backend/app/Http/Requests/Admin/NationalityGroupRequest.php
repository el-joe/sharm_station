<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NationalityGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-prices');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name.en' => ['required', 'string', 'max:80'],
            'name.it' => ['nullable', 'string', 'max:80'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'countries' => ['nullable', 'array', 'max:260'],
            'countries.*' => ['string', 'size:2', Rule::exists('countries', 'code')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name.en' => 'name', 'countries.*' => 'country'];
    }
}
