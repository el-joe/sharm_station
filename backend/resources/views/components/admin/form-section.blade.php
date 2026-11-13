@props(['title', 'description' => null])
<x-admin.card :title="$title" :subtitle="$description" {{ $attributes }}>
    <div class="grid grid-cols-1 gap-5 md:grid-cols-6">
        {{ $slot }}
    </div>
</x-admin.card>
