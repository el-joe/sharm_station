@php($title = $route->exists ? (string) $route->destination_name : 'New fixed route')
<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :breadcrumbs="['Transfers' => null, 'Fixed routes' => route('admin.transfers.routes.index'), $title => null]">
        <x-slot:actions><button type="submit" form="route-form" class="btn-cta">Save</button></x-slot:actions>
    </x-admin.page-header>
    <form id="route-form" method="POST" action="{{ $route->exists ? route('admin.transfers.routes.update', $route) : route('admin.transfers.routes.store') }}" novalidate>
        @csrf
        @if ($route->exists) @method('PUT') @endif
        <x-admin.form-section title="Fixed route" description="Destinations within the match radius get this price instead of the distance formula. Without a vehicle the price is multiplied by each vehicle's multiplier.">
            <x-admin.select name="transfer_hub_id" label="Hub" :options="$hubs" :selected="$route->transfer_hub_id" placeholder="Choose a hub" span="md:col-span-3" />
            <x-admin.select name="vehicle_type_id" label="Vehicle" :options="$vehicles" :selected="$route->vehicle_type_id" placeholder="All vehicles" data-s2-clear span="md:col-span-3" />
            <x-admin.translatable name="destination_name" label="Destination" :value="$route->getTranslations('destination_name')" required-locale="en" span="md:col-span-6" />
            <x-admin.input name="lat" label="Latitude" type="number" step="0.0000001" :value="$route->lat" span="md:col-span-2" />
            <x-admin.input name="lng" label="Longitude" type="number" step="0.0000001" :value="$route->lng" span="md:col-span-2" />
            <x-admin.input name="match_radius_km" label="Match radius (km)" type="number" step="0.1" min="0.1" :value="$route->match_radius_km" span="md:col-span-2" />
            <x-admin.money name="price" label="Base price" :value="$route->price" hint="Used when no group or season price is set" span="md:col-span-2" />
            <x-admin.switch name="is_enabled" label="Enabled" :checked="$route->is_enabled" span="md:col-span-2" />
        </x-admin.form-section>
    </form>

    <section id="price-matrix" class="mt-6">
        <h2 class="mb-3 font-display text-xl text-ink-900">Price matrix</h2>
        @if (! $route->exists)
            <x-admin.card><p class="text-sm text-ink-700">Save the route first, then set its prices per nationality group and season.</p></x-admin.card>
        @else
            @include('admin.partials.price-matrix', ['action' => route('admin.transfers.routes.seasons.update', $route), 'matrix' => $matrix])
        @endif
    </section>
</x-admin.layout>
