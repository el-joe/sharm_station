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

#[Name('get_trip')]
#[IsReadOnly]
#[Description('Full details of one trip by id: texts (summary, description, highlights, included/excluded, FAQ, policies), destination, duration, booking rules, unit options (per-unit trips such as buggies), private price tiers, day-by-day itinerary, price matrix (EUR base price per nationality group and seasons) and current SEO (slug, meta title/description, keywords, OG text per locale).')]
class GetTripTool extends Tool
{
    use ReadsCatalogue;

    public function handle(Request $request, McpCatalogueService $catalogue): Response|ResponseFactory
    {
        ['id' => $id, 'locale' => $locale] = $this->idAndLocale($request);

        return $this->found($catalogue->trip($id, $locale), "Trip {$id} not found. Use list_trips to find ids.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->min(1)->required()->description('Trip id from list_trips.'),
            'locale' => $this->localeSchema($schema),
        ];
    }
}
