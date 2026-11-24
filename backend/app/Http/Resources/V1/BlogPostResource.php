<?php

namespace App\Http\Resources\V1;

use App\Models\BlogPost;
use App\Services\ContentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Published blog post in the request locale (English fallback).
 *
 * @mixin BlogPost
 */
class BlogPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $seo = (array) ($this->seo ?? []);
        $locale = app()->getLocale();

        return [
            'slug' => $this->slug,
            'locale' => $locale,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'cover_image' => ContentService::coverUrl($this->cover_image),
            'body_html' => $this->body ?? '',
            'author' => $this->author,
            'published_at' => $this->published_at?->toIso8601String(),
            'tags' => array_values((array) ($this->tags ?? [])),
            'reading_minutes' => $this->reading_minutes,
            'related' => array_map(fn (array $link): array => [
                'label' => is_array($link['label'] ?? null) ? ($link['label'][$locale] ?? $link['label']['en'] ?? '') : (string) ($link['label'] ?? ''),
                'href' => $link['href'] ?? null,
            ], $this->related ?? []),
            'seo' => ['title' => $seo['title'] ?? $this->title, 'description' => $seo['description'] ?? $this->excerpt],
        ];
    }
}
