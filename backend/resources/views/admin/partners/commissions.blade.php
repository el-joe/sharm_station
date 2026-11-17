<x-admin.layout title="Commission override matrix">
    <x-admin.page-header title="Commission override matrix" :breadcrumbs="['Sales' => null, 'Partners' => route('admin.partners.index'), 'Override matrix' => null]"
                         subtitle="Partner price = customer price − commission %. Resolution: partner override › global setting (Settings → Commissions) › 0 %. Accommodation has no commission.">
        <x-slot:actions><button type="submit" form="commission-form" class="btn-cta">Save commissions</button></x-slot:actions>
    </x-admin.page-header>

    <form id="commission-form" method="POST" action="{{ route('admin.partners.commissions.update') }}" novalidate>
        @csrf @method('PUT')
        <x-admin.card :padding="false">
            <div class="overflow-x-auto">
                <table class="admin-table min-w-[860px]">
                    <thead>
                        <tr>
                            <th scope="col" class="sticky left-0 z-10 bg-sand-50">Partner</th>
                            @foreach ($categories as $category)<th scope="col">{{ $category->label() }} %</th>@endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($partners as $partner)
                            <tr>
                                <th scope="row" class="sticky left-0 z-10 border-t border-ink-100 bg-white px-4 py-3 text-left font-medium">
                                    {{ $partner->company }}
                                    <div class="mt-0.5"><x-admin.status-chip :status="$partner->status" /></div>
                                </th>
                                @foreach ($categories as $category)
                                    @php($key = "partners.{$partner->id}.categories.{$category->value}")
                                    <td>
                                        <input type="number" step="0.01" min="0" max="100" name="partners[{{ $partner->id }}][categories][{{ $category->value }}]" aria-label="{{ $partner->company }} {{ $category->label() }} commission"
                                               value="{{ old($key, $partner->commissionFor($category)) }}" placeholder="{{ $globals[$category->value] ?? 0 }}"
                                               @class(['form-control w-24', 'is-invalid' => $errors->has($key)])>
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($categories) + 1 }}" class="py-8 text-center text-ink-500">No active or pending partners.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="border-t border-ink-100 px-5 py-3 text-xs text-ink-500">Empty cells use the global setting (shown as placeholder). Suspended partners are hidden.</p>
        </x-admin.card>
    </form>
</x-admin.layout>
