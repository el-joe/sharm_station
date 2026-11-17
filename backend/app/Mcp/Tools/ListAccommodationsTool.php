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

#[Name('list_accommodations')]
#[IsReadOnly]
#[Description('List accommodation buildings (hotels, apartment buildings, villas, hostels), draft and published, 20 per page, each with a room summary (id, name, room type, capacity, EUR base price per night). Check this before suggesting a new accommodation, to avoid duplicates.')]
class ListAccommodationsTool extends Tool
{
    use ReadsCatalogue;

    public function handle(Request $request, McpCatalogueService $catalogue): ResponseFactory
    {
        return Response::structured($catalogue->listAccommodations($this->listQuery($request)));
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->listSchema($schema);
    }
}
