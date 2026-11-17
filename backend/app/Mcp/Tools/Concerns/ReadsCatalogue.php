<?php

namespace App\Mcp\Tools\Concerns;

use App\DTOs\Ai\CatalogueQueryData;
use App\Http\Middleware\SetLocaleAndCurrency;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * Shared arguments of the read tools: locale, and for list tools destination, q and page.
 */
trait ReadsCatalogue
{
    protected function localeSchema(JsonSchema $schema): Type
    {
        return $schema->string()->enum(SetLocaleAndCurrency::LOCALES)->description('Language of the texts: "en" (default) or "it".');
    }

    /**
     * @return array<string, Type>
     */
    protected function listSchema(JsonSchema $schema): array
    {
        return [
            'destination' => $schema->string()->description('Destination slug filter, e.g. "hurghada", "sharm-el-sheikh", "luxor".'),
            'q' => $schema->string()->description('Free-text search on the name/title, slug and destination (every word must match).'),
            'page' => $schema->integer()->min(1)->description('Page number, 20 items per page (default 1).'),
            'locale' => $this->localeSchema($schema),
        ];
    }

    /**
     * @param  array<string, mixed>  $extraRules
     */
    protected function listQuery(Request $request, array $extraRules = []): CatalogueQueryData
    {
        return CatalogueQueryData::fromArray($request->validate([
            'destination' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'locale' => ['nullable', Rule::in(SetLocaleAndCurrency::LOCALES)],
            ...$extraRules,
        ]));
    }

    /**
     * @return array{id: int, locale: string}
     */
    protected function idAndLocale(Request $request): array
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'locale' => ['nullable', Rule::in(SetLocaleAndCurrency::LOCALES)],
        ]);

        return ['id' => (int) $validated['id'], 'locale' => $validated['locale'] ?? 'en'];
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    protected function found(?array $data, string $notFound): Response|ResponseFactory
    {
        return $data === null ? Response::error($notFound) : Response::structured($data);
    }
}
