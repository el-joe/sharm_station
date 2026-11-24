<?php

namespace App\DTOs\Content;

use App\Enums\PublishStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final readonly class BlogPostData
{
    /**
     * @param  array<string, string>  $title
     * @param  array<string, string>  $excerpt
     * @param  array<string, string>  $body
     * @param  array<string, list<string>>  $tags
     * @param  array<string, array{title: string|null, description: string|null}>  $seo
     */
    public function __construct(
        public ?string $slug,
        public array $title,
        public PublishStatus $status,
        public array $excerpt = [],
        public array $body = [],
        public ?string $author = null,
        public array $tags = [],
        public array $seo = [],
        public ?CarbonImmutable $publishedAt = null,
        public ?UploadedFile $cover = null,
        public bool $removeCover = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            slug: filled($data['slug'] ?? null) ? (string) $data['slug'] : null,
            title: TranslatableInput::texts($data['title'] ?? []),
            status: PublishStatus::from($data['status']),
            excerpt: TranslatableInput::texts($data['excerpt'] ?? []),
            body: TranslatableInput::texts($data['body'] ?? []),
            author: filled($data['author'] ?? null) ? trim((string) $data['author']) : null,
            tags: TranslatableInput::tags($data['tags'] ?? []),
            seo: TranslatableInput::seo($data['seo'] ?? []),
            publishedAt: filled($data['published_at'] ?? null) ? CarbonImmutable::parse($data['published_at']) : null,
            cover: ($data['cover'] ?? null) instanceof UploadedFile ? $data['cover'] : null,
            removeCover: (bool) ($data['remove_cover'] ?? false),
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }
}
