<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateAccommodationSuggestionTool;
use App\Mcp\Tools\GetAccommodationTool;
use App\Mcp\Tools\GetPackageTool;
use App\Mcp\Tools\GetTripTool;
use App\Mcp\Tools\ListAccommodationsTool;
use App\Mcp\Tools\ListPackagesTool;
use App\Mcp\Tools\ListTransfersTool;
use App\Mcp\Tools\ListTripsTool;
use App\Mcp\Tools\SuggestSeoTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * Claude MCP server of Sharm Station Viaggi (CR-14), HTTP transport at /mcp (routes/ai.php).
 */
#[Name('sharm-station')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    Sharm Station Viaggi is an Egypt travel agency (Sharm El Sheikh, Hurghada, Cairo, Luxor, Aswan, Nile cruises).
    This server gives read access to the product catalogue and lets you PROPOSE changes for an admin to review.

    Read tools: list_trips / get_trip (single-day, multi-day and Nile trips), list_packages / get_package,
    list_transfers (airport/port hubs, fixed routes with prices, vehicles), list_accommodations / get_accommodation
    (buildings with rooms). Prices are in EUR per nationality group (e.g. egyptian, arab_countries, europe,
    rest_of_world) and season; a null group price means the product base price applies. Pass locale "it" for Italian
    texts (default "en"). Use the list tools to find ids, then the get tools for details.

    Write tools never change the website: create_accommodation_suggestion and suggest_seo store a PENDING suggestion
    that an admin edits and then approves or rejects in the back office (AI suggestions). Always read the current
    product first, then propose concise, factual SEO in both English and Italian. No customer, booking, payment or
    partner data is available through this server.
    MARKDOWN)]
class SharmStationServer extends Server
{
    protected array $tools = [
        ListTripsTool::class,
        GetTripTool::class,
        ListPackagesTool::class,
        GetPackageTool::class,
        ListTransfersTool::class,
        ListAccommodationsTool::class,
        GetAccommodationTool::class,
        CreateAccommodationSuggestionTool::class,
        SuggestSeoTool::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
