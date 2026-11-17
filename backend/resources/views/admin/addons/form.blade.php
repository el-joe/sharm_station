@php($title = $addon->exists ? (string) $addon->name : 'New add-on')
<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :breadcrumbs="['Catalogue' => null, 'Add-ons' => route('admin.addons.index'), $title => null]">
        <x-slot:actions><button type="submit" form="addon-form" class="btn-cta">Save</button></x-slot:actions>
    </x-admin.page-header>
    <form id="addon-form" method="POST" action="{{ $addon->exists ? route('admin.addons.update', $addon) : route('admin.addons.store') }}" novalidate class="space-y-4">
        @csrf
        @if ($addon->exists) @method('PUT') @endif
        <x-admin.form-section title="Add-on">
            <x-admin.translatable name="name" label="Name" :value="$addon->getTranslations('name')" required-locale="en" />
            <x-admin.translatable name="description" label="Description" type="textarea" :rows="3" :value="$addon->getTranslations('description')" />
        </x-admin.form-section>
        <x-admin.form-section title="Price and rules">
            <x-admin.money name="price" label="Price" :value="$addon->price" hint="Partners get the commission of the product's type" span="md:col-span-2" />
            <x-admin.select name="pricing_basis" label="Pricing basis" :options="\App\Enums\AddonPricingBasis::options()" :selected="$addon->pricing_basis" :select2="false" span="md:col-span-2" />
            <x-admin.input name="max_quantity" label="Max quantity" type="number" min="1" :value="$addon->max_quantity" span="md:col-span-2" />
            <x-admin.select name="booking_types[]" label="Booking types" :options="\App\Enums\BookingType::options()" :selected="$addon->booking_types ?? []" multiple placeholder="All booking types" span="md:col-span-4" />
            <x-admin.switch name="required" label="Required" description="Always added to the booking" :checked="$addon->required" span="md:col-span-3" />
            <x-admin.switch name="is_active" label="Active" :checked="$addon->is_active" span="md:col-span-3" />
        </x-admin.form-section>
    </form>
</x-admin.layout>
