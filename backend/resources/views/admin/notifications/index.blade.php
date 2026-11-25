<x-admin.layout title="Notifications">
    <x-admin.page-header title="Notifications" :breadcrumbs="['Settings' => null, 'Notifications' => null]" subtitle="Email templates per event and language. Customers receive emails in their booking language." />
    @include('admin.notifications._tabs', ['active' => 'templates'])

    <p class="mb-4 flex items-start gap-2 rounded-control bg-primary-100/60 px-4 py-3 text-sm text-ink-700">
        <x-admin.icon name="alert" size="18" class="mt-0.5 shrink-0" />
        <span>Admin emails go to: <strong>{{ $adminRecipients === [] ? 'nobody yet' : implode(', ', $adminRecipients) }}</strong>. Change the recipients in <a href="{{ route('admin.settings.edit') }}" class="btn-link">Settings → General</a>.</span>
    </p>

    <x-admin.card :padding="false">
        <div class="overflow-x-auto">
            <table class="admin-table min-w-[720px]">
                <thead><tr><th scope="col">Event</th><th scope="col">Recipient</th>@foreach ($locales as $locale)<th scope="col">{{ strtoupper($locale) }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($events as $event)
                        @php($rows = ($stored[$event->value] ?? collect())->keyBy('locale'))
                        <tr>
                            <td><span class="font-semibold text-ink-900">{{ $event->label() }}</span>@if ($event->attachesVoucher())<span class="chip ml-2 bg-brand-100 text-brand-700">Voucher PDF</span>@endif
                                <div class="font-mono text-xs text-ink-500">{{ $event->value }}</div></td>
                            <td class="text-sm">{{ $event->audience()->label() }}</td>
                            @foreach ($locales as $locale)
                                @php($row = $rows->get($locale))
                                <td class="whitespace-nowrap">
                                    <a href="{{ route('admin.notifications.edit', [$event, $locale]) }}" class="btn-link">Edit</a>
                                    <x-admin.status-chip :status="$row === null || $row->is_enabled ? 'enabled' : 'disabled'" :label="$row === null ? 'Default' : ($row->is_enabled ? 'Enabled' : 'Disabled')" class="ml-1" />
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.card>
</x-admin.layout>
