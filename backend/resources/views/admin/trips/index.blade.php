@php
    $label = $nile ? 'Nile trips' : 'Trips';
    $columns = array_values(array_filter([
        ['data' => 'title', 'name' => 'title', 'title' => 'Title'],
        ['data' => 'destination', 'name' => 'destination', 'title' => 'Destination', 'orderable' => false, 'searchable' => false],
        $nile ? null : ['data' => 'type', 'name' => 'type', 'title' => 'Type', 'searchable' => false],
        ['data' => 'pricing_mode', 'name' => 'pricing_mode', 'title' => 'Pricing mode', 'searchable' => false],
        auth()->user()->can('manage-prices') ? ['data' => 'from_price', 'name' => 'from_price', 'title' => 'From price', 'searchable' => false, 'className' => 'text-right tabular-nums'] : null,
        ['data' => 'status', 'name' => 'status', 'title' => 'Status', 'searchable' => false],
        ['data' => 'actions', 'name' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]));
@endphp
<x-admin.layout :title="$label">
    <x-admin.page-header :title="$label" :breadcrumbs="['Catalogue' => null, $label => null]"
                         :subtitle="$nile ? 'Nile cruises and dahabiyas: itinerary, vessel and cabins.' : 'Single-day and multi-day trips with per person or per unit pricing.'">
        <x-slot:actions>
            <a href="{{ route('admin.trips.create', $nile ? ['type' => 'nile'] : []) }}" class="btn-cta"><x-admin.icon name="plus" size="18" /> New {{ $nile ? 'Nile trip' : 'trip' }}</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form id="trip-filters" class="card mb-4 grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Filters">
        <input type="hidden" name="scope" value="{{ $nile ? 'nile' : 'trips' }}">
        <x-admin.select name="destination_id" label="Destination" :options="$destinations" placeholder="All destinations" data-s2-clear span="" />
        @unless ($nile)
            <x-admin.select name="type" label="Type" :options="collect(\App\Enums\TripType::options())->except('nile')->all()" placeholder="All types" data-s2-clear span="" />
        @endunless
        <x-admin.select name="pricing_mode" label="Pricing mode" :options="\App\Enums\PricingMode::options()" placeholder="Any" data-s2-clear span="" />
        <x-admin.select name="status" label="Status" :options="\App\Enums\PublishStatus::options()" placeholder="Any status" data-s2-clear span="" />
    </form>

    <x-admin.datatable id="trips-table" :url="route('admin.trips.data')" :columns="$columns" :order="[[0, 'asc']]" filters="#trip-filters"
                       search-placeholder="Search title or slug" :empty="'No '.strtolower($label).' yet.'" />
</x-admin.layout>
