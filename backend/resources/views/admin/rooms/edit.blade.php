@php
    use App\Enums\RoomPricingMode;
    use App\Enums\RoomStatus;
    use App\Enums\RoomType;
    $title = $room->exists ? (string) $room->name : 'New room';
@endphp
<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :breadcrumbs="['Accommodation' => null, 'Rooms' => route('admin.rooms.index', array_filter(['building_id' => $room->building_id])), $title => null]"
                         :subtitle="$room->exists ? ($room->building?->name.' · '.$room->room_type->label().($room->number ? ' · Room '.$room->number : '')) : null">
        <x-slot:actions>
            @if ($room->exists)
                <a href="{{ route('admin.rooms.calendar', $room) }}" class="btn-primary"><x-admin.icon name="calendar" size="18" /> Calendar</a>
            @endif
            <button type="submit" form="room-form" class="btn-cta">{{ $room->exists ? 'Save changes' : 'Create room' }}</button>
        </x-slot:actions>
    </x-admin.page-header>

    <div data-tabs data-tabs-hash>
        <div role="tablist" aria-label="Room editor sections" class="mb-5 flex overflow-x-auto overflow-y-hidden border-b border-ink-300/60">
            <button type="button" role="tab" id="tab-details-tab" aria-controls="tab-details" aria-selected="true" class="tab-link">Details</button>
            <button type="button" role="tab" id="tab-pricing-tab" aria-controls="tab-pricing" aria-selected="false" class="tab-link">Pricing</button>
            <button type="button" role="tab" id="tab-prices-tab" aria-controls="tab-prices" aria-selected="false" class="tab-link">Price matrix</button>
            @if ($room->exists)
                <button type="button" role="tab" id="tab-ical-tab" aria-controls="tab-ical" aria-selected="false" class="tab-link">Calendar sync</button>
            @endif
        </div>

        <form id="room-form" method="POST" action="{{ $room->exists ? route('admin.rooms.update', $room) : route('admin.rooms.store') }}" novalidate>
            @csrf
            @if ($room->exists) @method('PUT') @endif

            <div role="tabpanel" id="tab-details" aria-labelledby="tab-details-tab" class="space-y-4">
                <x-admin.form-section title="Room">
                    <x-admin.select name="building_id" label="Building" :options="$buildings" :selected="$room->building_id" placeholder="Choose a building" required span="md:col-span-3" />
                    <x-admin.select name="room_type" label="Room type" :options="RoomType::options()" :selected="$room->room_type" :select2="false" span="md:col-span-2" />
                    <x-admin.input name="number" label="Number" :value="$room->number" span="md:col-span-1" />
                    <x-admin.translatable name="name" label="Name" :value="$room->getTranslations('name')" span="md:col-span-4" required-locale="en" />
                    <x-admin.select name="status" label="Status" :options="RoomStatus::options()" :selected="$room->status" :select2="false" span="md:col-span-2" />
                </x-admin.form-section>
                <x-admin.form-section title="Capacity and beds">
                    <x-admin.input name="max_adults" label="Max adults" type="number" min="1" :value="$room->max_adults" span="md:col-span-2" />
                    <x-admin.input name="max_children" label="Max children" type="number" min="0" :value="$room->max_children" span="md:col-span-2" />
                    <x-admin.input name="max_total" label="Max total" type="number" min="1" :value="$room->max_total" span="md:col-span-2" />
                    <x-admin.translatable name="beds" label="Bed configuration" :value="$room->getTranslations('beds')" span="md:col-span-3" />
                    <x-admin.translatable name="view" label="View" :value="$room->getTranslations('view')" span="md:col-span-3" />
                    <x-admin.input name="size_m2" label="Size" type="number" min="1" suffix="m²" :value="$room->size_m2" span="md:col-span-2" />
                    <x-admin.input name="sort" label="Sort order" type="number" min="0" :value="$room->sort ?? 0" span="md:col-span-2" />
                </x-admin.form-section>
                <x-admin.form-section title="Description and amenities">
                    <x-admin.translatable name="description" label="Description" type="textarea" :rows="4" :value="$room->getTranslations('description')" />
                    <x-admin.select name="amenities[]" label="Amenities" :options="$amenities" :selected="$room->amenities ?? []" multiple tags placeholder="Add amenities" span="md:col-span-6" />
                </x-admin.form-section>
            </div>

            <div role="tabpanel" id="tab-pricing" aria-labelledby="tab-pricing-tab" class="space-y-4" hidden>
                @if ($canPrice)
                    <x-admin.form-section title="Base price per night" description="Product base price, used when neither a season nor the nationality group's base price (Price matrix tab) is set. Partners pay the customer price (no commission on accommodation). Tax-inclusive when the building's Taxes included flag is on.">
                        <x-admin.segmented name="pricing_mode" :selected="$room->pricing_mode ?? RoomPricingMode::PerRoom" :options="[
                            'per_room' => ['Per room', 'One price per night for the room'],
                            'per_person' => ['Per person', 'Price per guest per night'],
                        ]" />
                        <x-admin.money name="base_price" label="Base price" :value="$room->base_price" span="md:col-span-2" />
                        <x-admin.input name="min_nights" label="Minimum nights" type="number" min="1" :value="$room->min_nights" span="md:col-span-2" />
                        <x-admin.input name="max_nights" label="Maximum nights" type="number" min="1" :value="$room->max_nights" span="md:col-span-2" />
                    </x-admin.form-section>
                    <x-admin.form-section title="Extra beds and cots" description="Charged per night and per bed or cot, added to the stay quote (SRS 6.2, 7.3). Each extra bed adds one guest to the room's maximum total; cots do not change the capacity. 0 = not available.">
                        <x-admin.input name="max_extra_beds" label="Max extra beds" type="number" min="0" max="5" :value="$room->max_extra_beds ?? 0" span="md:col-span-2" />
                        <x-admin.money name="extra_bed_price" label="Extra bed per night" :value="$room->extra_bed_price" span="md:col-span-2" />
                        <span class="hidden md:col-span-2 md:block"></span>
                        <x-admin.input name="max_cots" label="Max cots" type="number" min="0" max="5" :value="$room->max_cots ?? 0" span="md:col-span-2" />
                        <x-admin.money name="cot_price" label="Cot per night" :value="$room->cot_price" span="md:col-span-2" />
                    </x-admin.form-section>
                @else
                    <x-admin.form-section title="Stay rules" description="Prices are managed by agents and super admins.">
                        <x-admin.input name="min_nights" label="Minimum nights" type="number" min="1" :value="$room->min_nights" span="md:col-span-2" />
                        <x-admin.input name="max_nights" label="Maximum nights" type="number" min="1" :value="$room->max_nights" span="md:col-span-2" />
                    </x-admin.form-section>
                @endif
            </div>
        </form>

        <div role="tabpanel" id="tab-prices" aria-labelledby="tab-prices-tab" hidden>
            @if (! $room->exists)
                <x-admin.card><p class="text-sm text-ink-700">Save the room first, then set its prices per nationality group and season.</p></x-admin.card>
            @elseif (! $canPrice)
                <x-admin.card><p class="text-sm text-ink-700">The price matrix is managed by agents and super admins.</p></x-admin.card>
            @else
                @include('admin.partials.price-matrix', ['action' => route('admin.rooms.seasons.update', $room), 'matrix' => $matrix])
            @endif
        </div>

        @if ($room->exists)
            <div role="tabpanel" id="tab-ical" aria-labelledby="tab-ical-tab" class="space-y-4" hidden>
                <x-admin.card title="Export (iCal)">
                    <p class="mb-3 text-sm text-ink-700">Paste this link in Booking.com, Airbnb or any channel manager: it lists the nights booked, held or closed here (nights imported from other calendars are not sent back). Keep it private.</p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input type="text" readonly value="{{ $icalUrl }}" id="ical-export-url" class="form-control flex-1 font-mono text-xs" aria-label="iCal export link">
                        <button type="button" class="btn-ghost" data-copy="#ical-export-url">Copy</button>
                        <form method="POST" action="{{ route('admin.rooms.ical.regenerate', $room) }}" data-confirm="The current link stops working. Create a new one?">
                            @csrf
                            <button type="submit" class="btn-ghost w-full">New link</button>
                        </form>
                    </div>
                </x-admin.card>

                <x-admin.card title="Import external calendars">
                    <p class="mb-3 text-sm text-ink-700">Every event of these calendars closes the room's nights. They are synced every 30 minutes; nights that disappear from a calendar are freed. Nights already booked, held or closed by hand are never changed.</p>
                    @if ($icalFeeds->isEmpty())
                        <p class="mb-4 text-sm text-ink-500">No external calendar yet.</p>
                    @else
                        <div class="mb-4 overflow-x-auto">
                            <table class="admin-table">
                                <thead><tr><th scope="col">Name</th><th scope="col">Last sync</th><th scope="col">Nights</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                                <tbody>
                                    @foreach ($icalFeeds as $feed)
                                        <tr>
                                            <td><span class="font-medium">{{ $feed->name }}</span><div class="max-w-md truncate text-xs text-ink-500" title="{{ $feed->url }}">{{ $feed->url }}</div></td>
                                            <td class="text-sm">{{ $feed->last_synced_at?->diffForHumans() ?? 'Never' }}@if ($feed->last_error)<div class="text-xs text-error">{{ $feed->last_error }}</div>@endif</td>
                                            <td class="tabular-nums">{{ $feed->last_nights }}</td>
                                            <td class="whitespace-nowrap text-right">
                                                <form method="POST" action="{{ route('admin.rooms.ical.sync', [$room, $feed]) }}" class="inline">@csrf<button type="submit" class="btn-link">Sync now</button></form>
                                                <form method="POST" action="{{ route('admin.rooms.ical.destroy', [$room, $feed]) }}" class="ml-3 inline" data-confirm="Remove “{{ $feed->name }}” and free the nights it closed?">@csrf @method('DELETE')<button type="submit" class="btn-link text-error">Remove</button></form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('admin.rooms.ical.store', $room) }}" class="grid grid-cols-1 gap-4 md:grid-cols-6" novalidate>
                        @csrf
                        <x-admin.input name="name" label="Name" placeholder="Booking.com" span="md:col-span-2" required />
                        <x-admin.input name="url" label="Calendar URL (.ics)" type="url" placeholder="https://…" span="md:col-span-4" required />
                        <div class="md:col-span-6"><button type="submit" class="btn-primary">Add and sync</button></div>
                    </form>
                </x-admin.card>
            </div>
        @endif
    </div>
</x-admin.layout>
