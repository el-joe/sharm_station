<?php

namespace App\Http\Resources\V1\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Practical information shared by trips and packages: FAQ, what to bring, guide languages, cancellation text.
 */
trait PresentsContent
{
    use PresentsLabels;

    /**
     * @return array{faq: list<array{question: string, answer: string}>, what_to_bring: list<string>, guide_languages: list<array{code: string, label: string}>, cancellation_policy: string|null}
     */
    protected function practicalInfo(Model $product): array
    {
        $faq = $product->getAttribute('faq');

        return [
            'faq' => array_values(array_filter(array_map(fn ($item): ?array => is_array($item) && filled($item['question'] ?? null) ? [
                'question' => (string) $item['question'],
                'answer' => (string) ($item['answer'] ?? ''),
            ] : null, is_array($faq) ? $faq : []))),
            'what_to_bring' => array_values((array) ($product->getAttribute('what_to_bring') ?? [])),
            'guide_languages' => $this->catalogLabels('languages', $product->getAttribute('guide_languages')),
            'cancellation_policy' => $product->getAttribute('cancellation_policy'),
        ];
    }
}
