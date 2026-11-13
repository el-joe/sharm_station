<x-admin.layout title="Buildings">
    <x-admin.page-header title="Buildings" :breadcrumbs="['Accommodation' => null, 'Buildings' => null]" subtitle="Hotels, apartment buildings, villas and hostels.">
        <x-slot:actions><a href="{{ route('admin.buildings.create') }}" class="btn-cta"><x-admin.icon name="plus" size="18" /> New building</a></x-slot:actions>
    </x-admin.page-header>
    <form id="building-filters" class="card mb-4 grid grid-cols-1 gap-3 p-4 sm:grid-cols-3" aria-label="Filters">
        <x-admin.select name="destination_id" label="Destination" :options="$destinations" placeholder="All destinations" data-s2-clear span="" />
    </form>
    <x-admin.datatable id="buildings-table" :url="route('admin.buildings.data')" filters="#building-filters" :order="[[0, 'asc']]" search-placeholder="Search name or address" :columns="[
        ['data' => 'name', 'name' => 'name', 'title' => 'Building'],
        ['data' => 'destination', 'name' => 'destination', 'title' => 'Destination', 'orderable' => false, 'searchable' => false],
        ['data' => 'stars', 'name' => 'stars', 'title' => 'Stars', 'searchable' => false],
        ['data' => 'rooms_count', 'name' => 'rooms_count', 'title' => 'Rooms', 'searchable' => false],
        ['data' => 'taxes_included', 'name' => 'taxes_included', 'title' => 'Taxes', 'searchable' => false],
        ['data' => 'status', 'name' => 'status', 'title' => 'Status', 'searchable' => false],
        ['data' => 'actions', 'name' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]" />
</x-admin.layout>
