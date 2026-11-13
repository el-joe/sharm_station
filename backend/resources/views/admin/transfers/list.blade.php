<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :breadcrumbs="['Transfers' => null, $title => null]" :subtitle="$subtitle">
        @if ($createUrl)
            <x-slot:actions><a href="{{ $createUrl }}" class="btn-cta"><x-admin.icon name="plus" size="18" /> {{ $createLabel }}</a></x-slot:actions>
        @endif
    </x-admin.page-header>
    <x-admin.datatable :id="$tableId" :url="$dataUrl" :order="[[0, 'asc']]" :columns="$columns" />
</x-admin.layout>
