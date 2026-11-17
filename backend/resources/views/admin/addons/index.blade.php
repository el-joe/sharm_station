<x-admin.layout title="Add-ons">
    <x-admin.page-header title="Add-ons" :breadcrumbs="['Catalogue' => null, 'Add-ons' => null]" subtitle="Reusable extras attached to trips and packages.">
        @if ($canPrice)
            <x-slot:actions><a href="{{ route('admin.addons.create') }}" class="btn-cta"><x-admin.icon name="plus" size="18" /> New add-on</a></x-slot:actions>
        @endif
    </x-admin.page-header>
    <x-admin.datatable id="addons-table" :url="route('admin.addons.data')" :order="[[0, 'asc']]" search-placeholder="Search add-ons" :columns="[
        ['data' => 'name', 'name' => 'name', 'title' => 'Name'],
        ['data' => 'pricing_basis', 'name' => 'pricing_basis', 'title' => 'Pricing basis', 'searchable' => false],
        ['data' => 'price', 'name' => 'price', 'title' => 'Price', 'searchable' => false, 'className' => 'text-right tabular-nums'],
        ['data' => 'trips_count', 'name' => 'trips_count', 'title' => 'Trips', 'searchable' => false],
        ['data' => 'is_active', 'name' => 'is_active', 'title' => 'Status', 'searchable' => false],
        ['data' => 'actions', 'name' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]" />
</x-admin.layout>
