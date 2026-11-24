<?php

namespace App\DTOs\Content;

use Illuminate\Foundation\Http\FormRequest;

final readonly class FaqCategoryData
{
    /**
     * @param  array<string, string>  $title
     */
    public function __construct(public array $title, public ?string $key = null) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(TranslatableInput::texts($request->validated('title', [])), $request->validated('key'));
    }
}
