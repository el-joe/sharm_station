<?php

namespace App\DTOs\Content;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Translatable page texts; "body" is raw editor HTML (sanitized by the ContentService).
 */
final readonly class PageData
{
    /**
     * @param  array<string, string>  $title
     * @param  array<string, string>  $intro
     * @param  array<string, string>  $body
     * @param  array<string, array{title: string|null, description: string|null}>  $seo
     */
    public function __construct(
        public array $title,
        public array $intro = [],
        public array $body = [],
        public array $seo = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: TranslatableInput::texts($data['title'] ?? []),
            intro: TranslatableInput::texts($data['intro'] ?? []),
            body: TranslatableInput::texts($data['body'] ?? []),
            seo: TranslatableInput::seo($data['seo'] ?? []),
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }
}
