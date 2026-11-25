@php
    $title = $option->label();
@endphp
<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :subtitle="'Optional room · '.$owner?->title"
                         :breadcrumbs="['Catalogue' => null, (string) $owner?->title => $indexUrl, $title => null]">
        <x-slot:actions>
            <a href="{{ $indexUrl }}" class="btn-ghost">All rooms</a>
            <button type="submit" form="room-option-form" class="btn-cta">Save</button>
        </x-slot:actions>
    </x-admin.page-header>

    <form id="room-option-form" method="POST" action="{{ route('admin.room-options.update', $option) }}" novalidate class="space-y-4">
        @csrf
        @method('PUT')
        @include('admin.room-options._fields', ['option' => $option, 'owner' => $owner])
    </form>

    <h2 class="mt-8 mb-3 font-display text-xl text-ink-900">Price matrix</h2>
    @include('admin.partials.price-matrix', ['action' => route('admin.room-options.seasons.update', $option), 'matrix' => $matrix])
</x-admin.layout>
