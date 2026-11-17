<?php

use App\Http\Middleware\AuthenticateMcpToken;
use App\Mcp\Servers\SharmStationServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| Claude MCP server (CR-14)
|--------------------------------------------------------------------------
| Streamable HTTP transport at /mcp. Staff-created Sanctum tokens with the "mcp" ability only
| (Settings → MCP access), rate limited per token ("mcp" limiter in AppServiceProvider).
*/

Mcp::web('/mcp', SharmStationServer::class)
    ->middleware([AuthenticateMcpToken::class, 'throttle:mcp'])
    ->name('mcp');
