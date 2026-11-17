<?php

namespace App\Mcp\Tools;

use App\Http\Middleware\SetLocaleAndCurrency;
use App\Mcp\Tools\Concerns\ReadsCatalogue;
use App\Services\McpCatalogueService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_transfers')]
#[IsReadOnly]
#[Description('Private transfer offer: every hub (airport, port, city) with its distance-based pricing (EUR base fare, distance tiers, minimum fare, night surcharge, extras), its fixed routes (destination, vehicle, price matrix per nationality group and season, SEO) and the vehicle types (capacity, luggage, price multiplier). Use the fixed route id with suggest_seo (subject_type "transfer_route").')]
class ListTransfersTool extends Tool
{
    use ReadsCatalogue;

    public function handle(Request $request, McpCatalogueService $catalogue): ResponseFactory
    {
        $validated = $request->validate(['locale' => ['nullable', Rule::in(SetLocaleAndCurrency::LOCALES)]]);

        return Response::structured($catalogue->transfers($validated['locale'] ?? 'en'));
    }

    public function schema(JsonSchema $schema): array
    {
        return ['locale' => $this->localeSchema($schema)];
    }
}
