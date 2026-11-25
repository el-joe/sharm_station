<?php

namespace App\Http\Requests\Api;

use App\Services\SlugRedirectService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /redirects/resolve?path=/en/trips/old-slug, or ?type=trip&slug=old-slug.
 */
class ResolveRedirectRequest extends FormRequest
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
        return [
            'path' => ['required_without:slug', 'nullable', 'string', 'max:300'],
            'type' => ['required_with:slug', 'nullable', Rule::in(array_unique(array_values(SlugRedirectService::SEGMENTS)))],
            'slug' => ['nullable', 'string', 'max:255'],
            'locale' => ['nullable', 'in:en,it'],
        ];
    }
}
