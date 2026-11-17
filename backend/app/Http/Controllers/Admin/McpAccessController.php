<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\McpTokenRequest;
use App\Services\McpTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Settings → MCP access (CR-14): staff tokens for Claude and connection instructions.
 */
class McpAccessController extends Controller
{
    public function __construct(private readonly McpTokenService $tokens) {}

    public function index(): View
    {
        return view('admin.settings.mcp', [
            'tokens' => $this->tokens->all(),
            'serverUrl' => rtrim((string) config('app.url'), '/').'/mcp',
            'tools' => ['list_trips', 'get_trip', 'list_packages', 'get_package', 'list_transfers', 'list_accommodations', 'get_accommodation', 'create_accommodation_suggestion', 'suggest_seo'],
            'serverName' => 'sharm-station',
        ]);
    }

    public function store(McpTokenRequest $request): RedirectResponse
    {
        $token = $this->tokens->create($request->user(), $request->validated('name'));

        return redirect()->route('admin.mcp.index')
            ->with('success', 'Token created. Copy it now: it is shown only once.')
            ->with('mcp_token', ['name' => $token->accessToken->name, 'value' => $token->plainTextToken]);
    }

    public function destroy(int $token): RedirectResponse
    {
        $this->tokens->revoke($token);

        return redirect()->route('admin.mcp.index')->with('success', 'Token revoked. Claude can no longer connect with it.');
    }
}
