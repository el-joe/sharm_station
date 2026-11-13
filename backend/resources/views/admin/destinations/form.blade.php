@php($title = $destination->exists ? (string) $destination->name : 'New destination')
<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :breadcrumbs="['Catalogue' => null, 'Destinations' => route('admin.destinations.index'), $title => null]">
        <x-slot:actions><button type="submit" form="destination-form" class="btn-cta">Save</button></x-slot:actions>
    </x-admin.page-header>
    <form id="destination-form" method="POST" action="{{ $destination->exists ? route('admin.destinations.update', $destination) : route('admin.destinations.store') }}" novalidate>
        @csrf
        @if ($destination->exists) @method('PUT') @endif
        <x-admin.form-section title="Destination">
            <x-admin.translatable name="name" label="Name" :value="$destination->getTranslations('name')" span="md:col-span-4" required-locale="en" />
            <x-admin.input name="slug" label="Slug" :value="$destination->slug" hint="Generated from the English name when empty." span="md:col-span-2" />
            <x-admin.translatable name="description" label="Description" type="textarea" :value="$destination->getTranslations('description')" />
            <x-admin.input name="image" label="Image path or URL" :value="$destination->image" span="md:col-span-4" />
            <x-admin.input name="sort" label="Sort order" type="number" min="0" :value="$destination->sort ?? 0" span="md:col-span-1" />
            <x-admin.switch name="is_active" label="Active" :checked="$destination->is_active" span="md:col-span-1" />
        </x-admin.form-section>
    </form>
</x-admin.layout>
