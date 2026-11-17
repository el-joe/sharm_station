@php
    use App\Services\McpTokenService;
    $newToken = session('mcp_token');
    $command = "claude mcp add --transport http {$serverName} {$serverUrl} --header \"Authorization: Bearer <your-token>\"";
    $json = json_encode(['mcpServers' => [$serverName => ['type' => 'http', 'url' => $serverUrl, 'headers' => ['Authorization' => 'Bearer <your-token>']]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
@endphp
<x-admin.layout title="MCP access">
    <x-admin.page-header title="Settings" :breadcrumbs="['Settings' => null, 'MCP access' => null]" subtitle="Global configuration of the platform." />

    <nav aria-label="Settings sections" class="mb-5 flex overflow-x-auto border-b border-ink-300/60">
        <a href="{{ route('admin.settings.edit') }}" class="tab-link">General</a>
        <a href="{{ route('admin.payment-methods.index') }}" class="tab-link">Payments</a>
        <a href="{{ route('admin.commissions.edit') }}" class="tab-link">Commissions</a>
        <a href="{{ route('admin.nationality-groups.index') }}" class="tab-link">Nationality groups</a>
        <a href="{{ route('admin.mcp.index') }}" class="tab-link" aria-selected="true" aria-current="page">MCP access</a>
    </nav>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <div class="space-y-4">
            <x-admin.card title="Access tokens" subtitle="Bearer tokens with the “mcp” ability. Each token is shown once when created.">
                <form method="POST" action="{{ route('admin.mcp.store') }}" class="mb-5 flex flex-wrap items-end gap-3" novalidate>
                    @csrf
                    <x-admin.input name="name" label="New token name" placeholder="Claude desktop – Marco" span="min-w-60 flex-1" maxlength="80" />
                    <button type="submit" class="btn-cta">Create token</button>
                </form>

                <div class="overflow-x-auto rounded-card border border-ink-100">
                    <table class="w-full text-sm">
                        <thead class="bg-ink-100/60 text-left text-xs tracking-wide text-ink-500 uppercase">
                            <tr>
                                <th scope="col" class="px-4 py-3">Name</th>
                                <th scope="col" class="px-4 py-3">Ability</th>
                                <th scope="col" class="px-4 py-3">Created</th>
                                <th scope="col" class="px-4 py-3">Last used</th>
                                <th scope="col" class="px-4 py-3">Status</th>
                                <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @forelse ($tokens as $token)
                                @php($revoked = McpTokenService::isRevoked($token))
                                <tr>
                                    <td class="px-4 py-3">
                                        <span class="font-semibold text-ink-900">{{ $token->name }}</span>
                                        <span class="block text-xs text-ink-500">by {{ $token->tokenable?->name ?? '—' }}</span>
                                    </td>
                                    <td class="px-4 py-3"><span class="chip bg-ink-100 text-ink-700">mcp</span></td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $token->created_at?->format('j M Y') }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $token->last_used_at?->format('j M Y, H:i') ?? 'Never' }}</td>
                                    <td class="px-4 py-3"><x-admin.status-chip :status="$revoked ? 'revoked' : 'active'" :tone="$revoked ? 'neutral' : 'success'" :label="$revoked ? 'Revoked' : 'Active'" /></td>
                                    <td class="px-4 py-3 text-right">
                                        @unless ($revoked)
                                            <form method="POST" action="{{ route('admin.mcp.destroy', $token->id) }}" data-confirm="Revoke “{{ $token->name }}”? Claude clients using it are disconnected immediately." data-confirm-title="Revoke token?" data-confirm-ok="Revoke">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn-danger btn-sm">Revoke</button>
                                            </form>
                                        @else
                                            <span class="text-ink-300">—</span>
                                        @endunless
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-6 text-center text-ink-500">No MCP tokens yet. Create one to connect Claude.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="mt-4 flex items-start gap-2 rounded-control bg-primary-100/60 px-4 py-3 text-sm text-ink-700">
                    <x-admin.icon name="alert" size="18" class="mt-0.5 shrink-0" />
                    Rate limit: 60 requests per minute per token. Read tools never return customer, booking, payment or partner data; write tools only create suggestions for review.
                </p>
            </x-admin.card>

            <x-admin.card title="Available tools" :subtitle="'Server “'.$serverName.'” · HTTP transport'">
                <ul class="flex flex-wrap gap-2">
                    @foreach ($tools as $tool)
                        <li @class(['chip font-mono', 'bg-warning/10 text-[#8a5f00]' => str_contains($tool, 'suggest'), 'bg-ink-100 text-ink-700' => ! str_contains($tool, 'suggest')])>{{ $tool }}</li>
                    @endforeach
                </ul>
            </x-admin.card>
        </div>

        <x-admin.card title="Connect Claude" subtitle="Use the server URL and a token from this page.">
            <label for="mcp-server-url" class="form-label">Server URL</label>
            <input id="mcp-server-url" type="text" readonly value="{{ $serverUrl }}" class="form-control bg-ink-100/60">

            <div class="mt-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-ink-900" style="font-family: var(--font-sans)">Claude Code (terminal)</h3>
                <button type="button" class="btn-link text-sm" data-copy="#mcp-command">Copy</button>
            </div>
            <pre id="mcp-command" class="mt-2 overflow-x-auto rounded-control bg-brand-navy-900 p-3 text-xs leading-relaxed whitespace-pre-wrap text-white">{{ $command }}</pre>

            <div class="mt-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-ink-900" style="font-family: var(--font-sans)">Claude Desktop / JSON config</h3>
                <button type="button" class="btn-link text-sm" data-copy="#mcp-json">Copy</button>
            </div>
            <pre id="mcp-json" class="mt-2 overflow-x-auto rounded-control bg-brand-navy-900 p-3 text-xs leading-relaxed text-white">{{ $json }}</pre>
            <p class="mt-3 text-xs text-ink-500">Replace &lt;your-token&gt; with a token from this page. Then ask Claude, for example: “Suggest SEO for the trip Giftun Island snorkelling in EN and IT”. Suggestions appear in Content → AI suggestions.</p>
        </x-admin.card>
    </div>

    @if ($newToken)
        <dialog data-dialog-auto aria-labelledby="token-dialog-title" class="m-auto w-full max-w-lg rounded-card p-0 shadow-xl backdrop:bg-brand-navy-900/50">
            <div class="p-6">
                <h2 id="token-dialog-title" class="text-xl text-ink-900">Token created</h2>
                <p class="mt-1 text-sm text-ink-500">{{ $newToken['name'] }} · ability “mcp”</p>
                <p class="mt-4 flex items-start gap-2 rounded-control bg-warning/10 px-4 py-3 text-sm text-[#8a5f00]">
                    <x-admin.icon name="alert" size="18" class="mt-0.5 shrink-0" />
                    Copy this token now. For security it is shown only once; if you lose it, revoke it and create a new one.
                </p>
                <label for="new-mcp-token" class="form-label mt-4">Your token</label>
                <div class="flex gap-2">
                    <input id="new-mcp-token" type="text" readonly value="{{ $newToken['value'] }}" class="form-control font-mono text-xs">
                    <button type="button" class="btn-primary" data-copy="#new-mcp-token" data-copy-message="Token copied.">Copy</button>
                </div>
                <div class="mt-5 flex justify-end"><button type="button" class="btn-cta" data-dialog-close>I copied it, close</button></div>
            </div>
        </dialog>
    @endif
</x-admin.layout>
