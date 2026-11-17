<?php

namespace App\Mcp\Tools;

use App\Enums\TripType;
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

#[Name('list_trips')]
#[IsReadOnly]
#[Description('List trips (single-day excursions, multi-day tours and Nile cruises), draft and published, 20 per page. Returns id, slug, type, status, title, destination, duration, pricing mode and the EUR "from" price. Use get_trip with the id for content, itinerary, prices per nationality group and season, and SEO.')]
class ListTripsTool extends Tool
{
    use ReadsCatalogue;

    public function handle(Request $request, McpCatalogueService $catalogue): ResponseFactory
    {
        return Response::structured($catalogue->listTrips($this->listQuery($request, [
            'type' => ['nullable', Rule::enum(TripType::class)],
        ])));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(TripType::values())->description('Trip type filter: single_day, multi_day or nile.'),
            ...$this->listSchema($schema),
        ];
    }
}
