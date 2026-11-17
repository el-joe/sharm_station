{{-- Subtitle line: source, token and creation time of a suggestion. --}}
Claude (MCP){{ $suggestion->token ? ' · token “'.$suggestion->token->name.'”' : '' }}{{ $suggestion->creator ? ' · '.$suggestion->creator->name : '' }} · {{ $suggestion->created_at->format('j M Y, H:i') }}
