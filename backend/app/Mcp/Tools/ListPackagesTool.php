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

#[Name('list_packages')]
#[IsReadOnly]
#[Description('List holiday packages (multi-stage tours combining trips and stays), draft and published, 20 per page. Returns id, slug, status, title, destination, days/nights, stage destinations and the EUR "from" price. The destination filter also matches stage destinations.')]
class ListPackagesTool extends Tool
{
    use ReadsCatalogue;

    public function handle(Request $request, McpCatalogueService $catalogue): ResponseFactory
    {
        return Response::structured($catalogue->listPackages($this->listQuery($request)));
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->listSchema($schema);
    }
}
