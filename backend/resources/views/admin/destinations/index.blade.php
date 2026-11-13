<x-admin.layout title="Destinations">
    <x-admin.page-header title="Destinations" :breadcrumbs="['Catalogue' => null, 'Destinations' => null]" subtitle="Places used by trips, packages and buildings.">
        <x-slot:actions><a href="{{ route('admin.destinations.create') }}" class="btn-cta"><x-admin.icon name="plus" size="18" /> New destination</a></x-slot:actions>
    </x-admin.page-header>
    <x-admin.datatable id="destinations-table" :url="route('admin.destinations.data')" :order="[[3, 'asc']]" search-placeholder="Search destinations" :columns="[
        ['data' => 'name', 'name' => 'name', 'title' => 'Name'],
        ['data' => 'trips_count', 'name' => 'trips_count', 'title' => 'Trips', 'searchable' => false],
        ['data' => 'buildings_count', 'name' => 'buildings_count', 'title' => 'Buildings', 'searchable' => false],
        ['data' => 'sort', 'name' => 'sort', 'title' => 'Sort', 'searchable' => false],
        ['data' => 'is_active', 'name' => 'is_active', 'title' => 'Status', 'searchable' => false],
        ['data' => 'actions', 'name' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]" />
</x-admin.layout>
