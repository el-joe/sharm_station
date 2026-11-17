<?php

namespace App\Http\Requests\Admin;

use App\DTOs\Ai\SeoValuesData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SEO suggestion values as edited on the review page. The same rules validate the MCP tool "suggest_seo".
 * Limits follow the trip SEO tab (title 70, description 170 characters).
 */
class SeoSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $locales = [];

        foreach ((array) $this->input('locales', []) as $locale => $values) {
            $values = (array) $values;
            $values['keywords'] = SeoValuesData::keywords($values['keywords'] ?? null);
            $locales[$locale] = $values;
        }

        $this->merge(['locales' => $locales, 'slug' => filled($this->input('slug')) ? trim((string) $this->input('slug')) : null]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::valueRules();
    }

    /**
     * @return array<string, mixed>
     */
    public static function valueRules(): array
    {
        return [
            'slug' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'locales' => ['present', 'array:en,it'],
            'locales.*' => ['array'],
            'locales.*.meta_title' => ['nullable', 'string', 'max:70'],
            'locales.*.meta_description' => ['nullable', 'string', 'max:170'],
            'locales.*.keywords' => ['nullable', 'array', 'max:20'],
            'locales.*.keywords.*' => ['string', 'max:60'],
            'locales.*.og_title' => ['nullable', 'string', 'max:95'],
            'locales.*.og_description' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::valueMessages();
    }

    /**
     * @return array<string, string>
     */
    public static function valueMessages(): array
    {
        return [
            'slug.regex' => 'The slug may contain only lowercase letters, numbers and dashes.',
            'locales.array' => 'Only the locales "en" and "it" are supported.',
        ];
    }
}
