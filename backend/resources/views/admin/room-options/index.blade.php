@php
    $isPackage = $owner instanceof \App\Models\Package;
    $section = $isPackage ? 'Packages' : ($owner->type === \App\Enums\TripType::Nile ? 'Nile trips' : 'Trips');
    $title = 'Optional rooms';
@endphp
<x-admin.layout :title="$title.' · '.$owner->title">
    <x-admin.page-header :title="$title" :subtitle="(string) $owner->title"
                         :breadcrumbs="['Catalogue' => null, $section => $isPackage ? route('admin.packages.index') : route('admin.trips.index'), (string) $owner->title => $editUrl, $title => null]">
        <x-slot:actions><a href="{{ $editUrl }}" class="btn-ghost">Back to the editor</a></x-slot:actions>
    </x-admin.page-header>

    @unless ($supported)
        <x-admin.card><p class="text-sm text-ink-700">Optional rooms are available only on multi-day products with at least one night (SRS 7.1). Set the trip's nights first.</p></x-admin.card>
    @else
        <x-admin.card title="Rooms offered" subtitle="Optional for the customer: every guest is allocated to one room of the booking (no shared rooms). No availability calendar.">
            @if ($options->isEmpty())
                <p class="text-sm text-ink-500">No room yet. Add the first one below.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="admin-table">
                        <thead><tr>
                            @if ($isPackage)<th scope="col">Stage</th>@endif
                            <th scope="col">Room</th><th scope="col">Type</th><th scope="col">Capacity</th><th scope="col">Price</th><th scope="col">Matrix</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th>
                        </tr></thead>
                        <tbody>
                            @foreach ($options as $option)
                                <tr>
                                    @if ($isPackage)<td>{{ $option->stage_position }}</td>@endif
                                    <td class="font-medium">{{ $option->label() }}</td>
                                    <td>{{ $option->unit_type->label() }}</td>
                                    <td>{{ $option->max_adults }} ad. · {{ $option->max_children }} ch. · max {{ $option->max_total }}</td>
                                    <td class="tabular-nums">€{{ number_format((float) $option->price, 2) }} <span class="text-xs text-ink-500">{{ strtolower($option->pricing_mode->label()) }}, {{ strtolower($option->price_period->label()) }}</span></td>
                                    <td class="text-xs text-ink-500">{{ $option->product_prices_count }} groups · {{ $option->season_prices_count }} seasons</td>
                                    <td><x-admin.status-chip :status="$option->is_active ? 'active' : 'inactive'" /></td>
                                    <td class="whitespace-nowrap text-right">
                                        <a href="{{ route('admin.room-options.edit', $option) }}" class="btn-link">Edit</a>
                                        <form method="POST" action="{{ route('admin.room-options.destroy', $option) }}" class="ml-3 inline" data-confirm="Remove “{{ $option->label() }}”? Existing bookings keep it.">@csrf @method('DELETE')<button type="submit" class="btn-link text-error">Remove</button></form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.card>

        <form method="POST" action="{{ $storeUrl }}" novalidate class="mt-4 space-y-4">
            @csrf
            <h2 class="font-display text-xl text-ink-900">Add a room</h2>
            @include('admin.room-options._fields', ['option' => $blank, 'owner' => $owner])
            <button type="submit" class="btn-cta">Add room</button>
        </form>
    @endunless
</x-admin.layout>
