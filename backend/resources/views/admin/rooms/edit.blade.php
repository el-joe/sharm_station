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
            <button type="button" role="tab" id="tab-prices-tab" aria-controls="tab-prices" aria-selected="false" class="tab-link">Price list</button>
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
                    <x-admin.form-section title="Base price per night" description="Used when no season matches the night. Tax-inclusive when the building's Taxes included flag is on.">
                        <x-admin.segmented name="pricing_mode" :selected="$room->pricing_mode ?? RoomPricingMode::PerRoom" :options="[
                            'per_room' => ['Per room', 'One price per night for the room'],
                            'per_person' => ['Per person', 'Price per guest per night'],
                        ]" />
                        <x-admin.money name="base_price" label="Base price" :value="$room->base_price" retail-for="room" span="md:col-span-2" />
                        <x-admin.money name="partner_price" label="Partner price (optional)" :value="$room->partner_price" partner-for="room" :commission="$commission" hint="Overrides the commission rule" span="md:col-span-2" />
                        <x-admin.money name="extra_bed_price" label="Extra bed" :value="$room->extra_bed_price" span="md:col-span-2" />
                        <x-admin.input name="min_nights" label="Minimum nights" type="number" min="1" :value="$room->min_nights" span="md:col-span-2" />
                        <x-admin.input name="max_nights" label="Maximum nights" type="number" min="1" :value="$room->max_nights" span="md:col-span-2" />
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
                <x-admin.card><p class="text-sm text-ink-700">Save the room first, then add its seasons.</p></x-admin.card>
            @elseif (! $canPrice)
                <x-admin.card><p class="text-sm text-ink-700">The price list is managed by agents and super admins.</p></x-admin.card>
            @else
                @include('admin.partials.season-editor', ['action' => route('admin.rooms.seasons.update', $room), 'seasons' => $seasons, 'columns' => [
                    ['label' => 'Price per night', 'key' => 'price_per_night'],
                    ['label' => 'Partner price', 'key' => 'partner_price', 'partner' => true],
                ]])
            @endif
        </div>
    </div>
</x-admin.layout>
