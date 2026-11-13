@php
    use App\Enums\BuildingType;
    use App\Enums\PublishStatus;
    $title = $building->exists ? (string) $building->name : 'New building';
@endphp
<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :breadcrumbs="['Accommodation' => null, 'Buildings' => route('admin.buildings.index'), $title => null]"
                         :subtitle="$building->exists ? $building->type->label().' · '.($building->destination?->name ?? '').' · '.$building->rooms_count.' rooms' : null">
        <x-slot:actions>
            @if ($building->exists)
                <a href="{{ route('admin.rooms.index', ['building_id' => $building->id]) }}" class="btn-primary">Rooms</a>
            @endif
            <button type="submit" form="building-form" class="btn-cta">{{ $building->exists ? 'Save changes' : 'Create building' }}</button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
        <form id="building-form" method="POST" action="{{ $building->exists ? route('admin.buildings.update', $building) : route('admin.buildings.store') }}" class="space-y-4" novalidate>
            @csrf
            @if ($building->exists) @method('PUT') @endif

            <x-admin.form-section title="Basics">
                <x-admin.translatable name="name" label="Name" :value="$building->getTranslations('name')" span="md:col-span-4" required-locale="en" />
                <x-admin.input name="slug" label="Slug" :value="$building->slug" hint="Generated from the English name when empty." span="md:col-span-2" />
                <x-admin.select name="type" label="Type" :options="BuildingType::options()" :selected="$building->type" :select2="false" span="md:col-span-2" />
                <x-admin.select name="destination_id" label="Destination" :options="$destinations" :selected="$building->destination_id" placeholder="Choose a destination" required span="md:col-span-2" />
                <x-admin.select name="status" label="Status" :options="PublishStatus::options()" :selected="$building->status" :select2="false" span="md:col-span-2" />
                <x-admin.select name="stars" label="Stars" :options="[1 => '★', 2 => '★★', 3 => '★★★', 4 => '★★★★', 5 => '★★★★★']" :selected="$building->stars" placeholder="—" :select2="false" span="md:col-span-2" />
                <x-admin.switch name="featured" label="Featured" description="Shown on the home page" :checked="$building->featured" span="md:col-span-6" />
            </x-admin.form-section>

            <x-admin.form-section title="Location">
                <x-admin.input name="address" label="Address" :value="$building->address" span="md:col-span-6" />
                <x-admin.input name="lat" label="Latitude" type="number" step="0.0000001" :value="$building->lat" span="md:col-span-3" />
                <x-admin.input name="lng" label="Longitude" type="number" step="0.0000001" :value="$building->lng" span="md:col-span-3" />
            </x-admin.form-section>

            <x-admin.form-section title="Description and amenities">
                <x-admin.translatable name="description" label="Description" type="textarea" :rows="5" :value="$building->getTranslations('description')" />
                <x-admin.select name="amenities[]" label="Amenities" :options="$amenities" :selected="$building->amenities ?? []" multiple tags placeholder="Add amenities" hint="Pick from the list or type a new one." span="md:col-span-6" />
                <x-admin.input name="check_in_from" label="Check-in from" type="time" :value="$building->check_in_from ? substr($building->check_in_from, 0, 5) : null" span="md:col-span-3" />
                <x-admin.input name="check_out_until" label="Check-out until" type="time" :value="$building->check_out_until ? substr($building->check_out_until, 0, 5) : null" span="md:col-span-3" />
            </x-admin.form-section>

            <x-admin.form-section title="Policies and taxes" description="Taxes included (CR-2): every price includes VAT, tourism tax and city fees and the site shows a “Tax included” badge.">
                @if ($canPrice)
                    <x-admin.switch name="taxes_included" label="Taxes included" description="Turn off to charge a tax per night as a separate line" :checked="$building->taxes_included" data-disables="#f-tax_per_night" span="md:col-span-3" />
                    <x-admin.money name="tax_per_night" label="Tax per night" :value="$building->tax_per_night" hint="Disabled while taxes are included" span="md:col-span-3" />
                    <x-admin.input name="deposit_percent" label="Deposit" type="number" step="0.01" min="0" max="100" suffix="%" :value="$building->deposit_percent" span="md:col-span-2" />
                @else
                    <p class="text-sm text-ink-700 md:col-span-6">Taxes: <strong>{{ $building->taxes_included ? 'included' : '€'.number_format((float) $building->tax_per_night, 2).' per night' }}</strong> · Deposit {{ (float) $building->deposit_percent }}% (managed by agents).</p>
                @endif
                <x-admin.translatable name="cancellation_policy" label="Cancellation policy" type="textarea" :rows="3" :value="$building->getTranslations('cancellation_policy')" />
            </x-admin.form-section>

            <x-admin.form-section title="Google reviews and contact">
                <x-admin.input name="google_place_id" label="Google Place ID" :value="$building->google_place_id" placeholder="ChIJ…" hint="Reviews of this building are read from Google Places (SRS 13.4)." span="md:col-span-6" />
                <x-admin.input name="phone" label="Phone (internal)" :value="$building->phone" span="md:col-span-3" />
                <x-admin.input name="email" label="Email (internal)" type="email" :value="$building->email" span="md:col-span-3" />
            </x-admin.form-section>
        </form>

        <aside class="space-y-4">
            <x-admin.card title="Google reviews">
                @if ($building->google_place_id)
                    <p class="text-3xl font-bold text-ink-900">{{ $rating['rating'] ?? '—' }} <span class="text-base font-medium text-gold-500">★</span></p>
                    <p class="text-sm text-ink-700">{{ $rating['total'] ?? 0 }} reviews on Google</p>
                    <p class="mt-1 truncate text-xs text-ink-500" title="{{ $building->google_place_id }}">{{ $building->google_place_id }}</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" class="btn-primary btn-sm" data-ajax-action="{{ route('admin.buildings.reviews.sync', $building) }}" data-busy="Syncing…" data-reload>
                            <x-admin.icon name="refresh" size="16" /> Sync now
                        </button>
                        <a href="{{ route('admin.google-reviews.index', ['place_id' => $building->google_place_id]) }}" class="btn-ghost btn-sm">Manage reviews</a>
                    </div>
                @else
                    <p class="text-sm text-ink-700">Add the building's Google Place ID and save to show its Google rating and reviews.</p>
                @endif
            </x-admin.card>
            @if ($building->exists)
                <x-admin.card title="Danger zone">
                    <form method="POST" action="{{ route('admin.buildings.destroy', $building) }}" data-confirm="Delete “{{ $title }}” and all its rooms? This cannot be undone.">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-danger btn-sm">Delete building</button>
                    </form>
                </x-admin.card>
            @endif
        </aside>
    </div>
</x-admin.layout>
