<x-admin.layout title="Packages">
    <x-admin.page-header title="Packages" :breadcrumbs="['Catalogue' => null, 'Packages' => null]" subtitle="Multi-destination programmes built from existing trips. Transfers are always included.">
        @if ($canPrice)
            <x-slot:actions><a href="{{ route('admin.packages.create') }}" class="btn-cta"><x-admin.icon name="plus" size="18" /> New package</a></x-slot:actions>
        @endif
    </x-admin.page-header>
    <x-admin.datatable id="packages-table" :url="route('admin.packages.data')" :order="[[0, 'asc']]" search-placeholder="Search packages" :columns="[
        ['data' => 'title', 'name' => 'title', 'title' => 'Package'],
        ['data' => 'destination', 'name' => 'destination', 'title' => 'Destination', 'orderable' => false, 'searchable' => false],
        ['data' => 'stages_count', 'name' => 'stages_count', 'title' => 'Stages', 'searchable' => false],
        ['data' => 'included_transfers_count', 'name' => 'included_transfers_count', 'title' => 'Transfers', 'searchable' => false],
        ['data' => 'price_mode', 'name' => 'price_mode', 'title' => 'Price mode', 'searchable' => false],
        ['data' => 'from_price', 'name' => 'from_price', 'title' => 'From', 'searchable' => false, 'className' => 'text-right tabular-nums'],
        ['data' => 'status', 'name' => 'status', 'title' => 'Status', 'searchable' => false],
        ['data' => 'actions', 'name' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]" />
</x-admin.layout>
