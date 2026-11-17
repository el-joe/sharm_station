<?php

namespace App\Http\Middleware;

use App\Services\McpTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Enums\ErrorCode;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Claude MCP endpoint (/mcp): a Sanctum bearer token that explicitly carries the "mcp" ability and belongs to
 * a staff user. Wildcard ("*") tokens, customer/partner tokens, revoked tokens and session cookies are refused.
 */
class AuthenticateMcpToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->bearerToken() !== null ? Auth::guard('sanctum')->user() : null;
        $token = $user?->currentAccessToken();

        if (! McpTokenService::grantsMcpAccess($token instanceof PersonalAccessToken ? $token : null, $user)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => [
                    'code' => ErrorCode::INVALID_REQUEST->value,
                    'message' => 'Unauthorized: send "Authorization: Bearer <token>" with an MCP access token created in the admin (Settings → MCP access).',
                ],
            ], 401, ['WWW-Authenticate' => 'Bearer realm="sharm-station-mcp"']);
        }

        Auth::setUser($user);

        return $next($request);
    }
}
