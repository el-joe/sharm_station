<x-admin.layout title="AI suggestions">
    <x-admin.page-header title="AI suggestions" :breadcrumbs="['Content' => null, 'AI suggestions' => null]"
                         subtitle="Created by Claude through the MCP server. Nothing is published until an admin approves it.">
        @can('manage-settings')
            <x-slot:actions><a href="{{ route('admin.mcp.index') }}" class="btn-ghost">MCP access</a></x-slot:actions>
        @endcan
    </x-admin.page-header>

    <nav aria-label="Suggestion status" class="mb-4 flex flex-wrap gap-2">
        @foreach (['' => 'All'] + $statuses as $value => $label)
            @php($active = ($status ?? '') === $value)
            <a href="{{ route('admin.ai-suggestions.index', ['status' => $value === '' ? 'all' : $value]) }}" @if ($active) aria-current="page" @endif
               @class(['inline-flex items-center gap-2 rounded-full border px-4 py-1.5 text-sm font-medium', 'border-brand-navy-900 bg-brand-navy-900 text-white' => $active, 'border-ink-300 bg-white text-ink-900 hover:bg-ink-100' => ! $active])>
                {{ $label }}
                <span @class(['rounded-full px-2 text-xs tabular-nums', 'bg-white text-brand-navy-900' => $active, 'bg-ink-100 text-ink-700' => ! $active])>{{ $counts[$value === '' ? 'all' : $value] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    <form id="suggestion-filters" class="card mb-4 p-4" aria-label="Filters">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-admin.select name="type" label="Type" :options="$types" placeholder="All types" :select2="false" span="" />
            <x-admin.select name="status" label="Status" :options="$statuses" :selected="$status" placeholder="All statuses" :select2="false" span="" />
        </div>
        <div class="mt-3 flex justify-end"><button type="submit" class="btn-primary btn-sm">Apply</button></div>
    </form>

    <x-admin.datatable id="ai-suggestions-table" :url="route('admin.ai-suggestions.data')" filters="#suggestion-filters" :order="[[0, 'desc']]"
                       search-placeholder="Subject or ID" empty="No suggestions yet. Connect Claude in Settings → MCP access." :columns="[
        ['data' => 'id', 'name' => 'id', 'title' => '#', 'searchable' => false],
        ['data' => 'type', 'name' => 'type', 'title' => 'Type', 'searchable' => false],
        ['data' => 'subject', 'name' => 'subject', 'title' => 'Subject', 'orderable' => false],
        ['data' => 'source', 'name' => 'source', 'title' => 'Source', 'orderable' => false, 'searchable' => false],
        ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Created', 'searchable' => false],
        ['data' => 'status', 'name' => 'status', 'title' => 'Status', 'searchable' => false],
        ['data' => 'actions', 'name' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]" />
</x-admin.layout>
