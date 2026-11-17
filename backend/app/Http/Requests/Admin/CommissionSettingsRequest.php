<?php

namespace App\Http\Requests\Admin;

use App\Enums\CommissionCategory;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Global commission percentage per type (Settings → Commissions). Every type is required (0–100).
 */
class CommissionSettingsRequest extends FormRequest
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
        $rules = ['commissions' => ['required', 'array']];

        foreach (CommissionCategory::cases() as $category) {
            $rules["commissions.{$category->value}"] = ['required', 'numeric', 'min:0', 'max:100'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (CommissionCategory::cases() as $category) {
            $attributes["commissions.{$category->value}"] = strtolower($category->label()).' commission';
        }

        return $attributes;
    }
}
