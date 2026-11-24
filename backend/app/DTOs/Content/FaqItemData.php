<?php

namespace App\DTOs\Content;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FAQ entry; "answer" is raw editor HTML (sanitized by the ContentService).
 */
final readonly class FaqItemData
{
    /**
     * @param  array<string, string>  $question
     * @param  array<string, string>  $answer
     */
    public function __construct(
        public int $categoryId,
        public array $question,
        public array $answer,
        public bool $isActive = true,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            categoryId: (int) $request->validated('faq_category_id'),
            question: TranslatableInput::texts($request->validated('question', [])),
            answer: TranslatableInput::texts($request->validated('answer', [])),
            isActive: (bool) $request->validated('is_active', true),
        );
    }
}
