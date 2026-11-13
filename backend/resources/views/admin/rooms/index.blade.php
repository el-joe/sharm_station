@php
    $columns = array_values(array_filter([
        ['data' => 'name', 'name' => 'name', 'title' => 'Room'],
        ['data' => 'building', 'name' => 'building', 'title' => 'Building', 'orderable' => false, 'searchable' => false],
        ['data' => 'room_type', 'name' => 'room_type', 'title' => 'Type', 'searchable' => false],
        ['data' => 'capacity', 'name' => 'capacity', 'title' => 'Capacity', 'orderable' => false, 'searchable' => false],
        $canPrice ? ['data' => 'base_price', 'name' => 'base_price', 'title' => 'Base price', 'searchable' => false, 'className' => 'text-right tabular-nums'] : null,
        ['data' => 'status', 'name' => 'status', 'title' => 'Status', 'searchable' => false],
        ['data' => 'actions', 'name' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]));
@endphp
<x-admin.layout title="Rooms">
    <x-admin.page-header title="Rooms" :breadcrumbs="['Accommodation' => null, 'Rooms' => null]" subtitle="Exclusive units inside buildings, priced per night.">
        <x-slot:actions><a href="{{ route('admin.rooms.create', array_filter(['building_id' => $buildingId])) }}" class="btn-cta"><x-admin.icon name="plus" size="18" /> New room</a></x-slot:actions>
    </x-admin.page-header>
    <form id="room-filters" class="card mb-4 grid grid-cols-1 gap-3 p-4 sm:grid-cols-3" aria-label="Filters">
        <x-admin.select name="building_id" label="Building" :options="$buildings" :selected="$buildingId" placeholder="All buildings" data-s2-clear span="" />
    </form>
    <x-admin.datatable id="rooms-table" :url="route('admin.rooms.data')" filters="#room-filters" :order="[[0, 'asc']]" search-placeholder="Search name or number" :columns="$columns" />
</x-admin.layout>
