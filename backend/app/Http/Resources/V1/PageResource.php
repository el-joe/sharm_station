<?php

namespace App\Http\Resources\V1;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Static page in the request locale (English fallback).
 *
 * @mixin Page
 */
class PageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $seo = (array) ($this->seo ?? []);

        return [
            'slug' => $this->slug,
            'locale' => app()->getLocale(),
            'title' => $this->title,
            'intro' => $this->intro,
            'body_html' => $this->body ?? '',
            'seo' => ['title' => $seo['title'] ?? $this->title, 'description' => $seo['description'] ?? $this->intro],
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
