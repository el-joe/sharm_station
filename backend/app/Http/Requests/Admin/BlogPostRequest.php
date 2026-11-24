<?php

namespace App\Http\Requests\Admin;

use App\Enums\PublishStatus;
use App\Models\BlogPost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-content');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['remove_cover' => $this->boolean('remove_cover')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $post = $this->route('post');

        return [
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('blog_posts', 'slug')->ignore($post instanceof BlogPost ? $post->id : null)],
            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:160'],
            'title.it' => ['nullable', 'string', 'max:160'],
            'excerpt' => ['nullable', 'array'],
            'excerpt.*' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'array'],
            'body.*' => ['nullable', 'string', 'max:200000'],
            'author' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['nullable', 'string', 'max:300'],
            'seo' => ['nullable', 'array'],
            'seo.*.title' => ['nullable', 'string', 'max:70'],
            'seo.*.description' => ['nullable', 'string', 'max:170'],
            'status' => ['required', Rule::enum(PublishStatus::class)],
            'published_at' => ['nullable', 'date'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=600'],
            'remove_cover' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['slug.regex' => 'Use lowercase letters, numbers and dashes.', 'cover.dimensions' => 'The cover must be at least 600 px wide.'];
    }
}
