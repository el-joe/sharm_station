<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ReadsCatalogue;
use App\Services\McpCatalogueService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_accommodation')]
#[IsReadOnly]
#[Description('Full details of one accommodation building by id: address, coordinates, stars, description, amenities, check-in/out, taxes, policies and SEO, plus every room with capacity, beds, size, amenities, min/max nights, price matrix (EUR per night per nationality group and season) and room SEO.')]
class GetAccommodationTool extends Tool
{
    use ReadsCatalogue;

    public function handle(Request $request, McpCatalogueService $catalogue): Response|ResponseFactory
    {
        ['id' => $id, 'locale' => $locale] = $this->idAndLocale($request);

        return $this->found($catalogue->accommodation($id, $locale), "Accommodation {$id} not found. Use list_accommodations to find ids.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->min(1)->required()->description('Building id from list_accommodations.'),
            'locale' => $this->localeSchema($schema),
        ];
    }
}
