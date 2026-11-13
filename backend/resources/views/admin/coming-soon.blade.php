<x-admin.layout :title="$module">
    <x-admin.page-header :title="$module" subtitle="This module is part of the SRS but is not built in this phase." />
    <x-admin.card>
        <div class="flex flex-col items-center py-10 text-center">
            <span class="grid size-14 place-items-center rounded-full bg-brand-100 text-brand-700"><x-admin.icon name="clock" size="28" /></span>
            <h2 class="mt-4 text-xl">Coming soon</h2>
            <p class="mt-2 max-w-md text-sm text-ink-700">{{ $module }} will be delivered in a later phase. Use the dashboard and bookings list in the meantime.</p>
            <a href="{{ route('admin.dashboard') }}" class="btn-primary mt-6">Back to overview</a>
        </div>
    </x-admin.card>
</x-admin.layout>
