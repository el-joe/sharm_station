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

#[Name('get_package')]
#[IsReadOnly]
#[Description('Full details of one package by id: texts, stages (destination, days, nights, accommodation, linked trips), included transfers, price matrix (EUR adult/child price per nationality group and seasons) and current SEO per locale.')]
class GetPackageTool extends Tool
{
    use ReadsCatalogue;

    public function handle(Request $request, McpCatalogueService $catalogue): Response|ResponseFactory
    {
        ['id' => $id, 'locale' => $locale] = $this->idAndLocale($request);

        return $this->found($catalogue->package($id, $locale), "Package {$id} not found. Use list_packages to find ids.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->min(1)->required()->description('Package id from list_packages.'),
            'locale' => $this->localeSchema($schema),
        ];
    }
}
