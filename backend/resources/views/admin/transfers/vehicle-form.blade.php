@php($title = $vehicle->exists ? (string) $vehicle->name : 'New vehicle')
<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :breadcrumbs="['Transfers' => null, 'Vehicles' => route('admin.transfers.vehicles.index'), $title => null]">
        <x-slot:actions><button type="submit" form="vehicle-form" class="btn-cta">Save</button></x-slot:actions>
    </x-admin.page-header>
    <form id="vehicle-form" method="POST" action="{{ $vehicle->exists ? route('admin.transfers.vehicles.update', $vehicle) : route('admin.transfers.vehicles.store') }}" novalidate>
        @csrf
        @if ($vehicle->exists) @method('PUT') @endif
        <x-admin.form-section title="Vehicle" description="The multiplier V scales the distance price; the minimum fare overrides the hub minimum.">
            <x-admin.translatable name="name" label="Name" :value="$vehicle->getTranslations('name')" required-locale="en" span="md:col-span-4" />
            <x-admin.select name="category" label="Category" :options="\App\Enums\VehicleType::options()" :selected="$vehicle->category" :select2="false" span="md:col-span-2" />
            <x-admin.input name="capacity" label="Passengers" type="number" min="1" :value="$vehicle->capacity" span="md:col-span-1" />
            <x-admin.input name="luggage" label="Luggage" type="number" min="0" :value="$vehicle->luggage" span="md:col-span-1" />
            <x-admin.input name="multiplier" label="Multiplier" type="number" step="0.01" min="0.1" :value="$vehicle->multiplier" span="md:col-span-1" />
            <x-admin.money name="min_fare" label="Minimum fare (optional)" :value="$vehicle->min_fare" span="md:col-span-2" />
            <x-admin.input name="sort" label="Order" type="number" min="0" :value="$vehicle->sort ?? 0" span="md:col-span-1" />
            <x-admin.input name="image" label="Image path" :value="$vehicle->image" span="md:col-span-4" />
            <x-admin.switch name="is_enabled" label="Enabled" :checked="$vehicle->is_enabled" span="md:col-span-2" />
        </x-admin.form-section>
    </form>
</x-admin.layout>
