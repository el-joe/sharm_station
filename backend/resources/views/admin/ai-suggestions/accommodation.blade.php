@php
    $payload = $suggestion->payload ?? [];
    $building = $payload['building'] ?? [];
    $pending = $suggestion->isPending();
    $editable = $pending && $canApprove;
    $rooms = array_values(old('rooms', $payload['rooms'] ?? []));
    $title = 'Accommodation suggestion · '.($building['name']['en'] ?? 'Untitled');
    $amenityOptions = collect(\App\Http\Controllers\Admin\BuildingController::AMENITIES);
    foreach ((array) old('building.amenities', $building['amenities'] ?? []) as $amenity) {
        $amenityOptions[$amenity] ??= $amenity;
    }
@endphp
<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :breadcrumbs="['Content' => null, 'AI suggestions' => route('admin.ai-suggestions.index'), '#'.$suggestion->id => null]">
        <x-slot:subtitle>@include('admin.ai-suggestions.partials.meta')</x-slot:subtitle>
        <x-slot:actions>
            <x-admin.status-chip :status="$suggestion->status" class="text-sm" />
            @if ($suggestion->subject)<a href="{{ route('admin.buildings.edit', $suggestion->subject_id) }}" class="btn-link">Open building</a>@endif
        </x-slot:actions>
    </x-admin.page-header>

    @if (! empty($payload['notes']))
        <p class="mb-4 rounded-control bg-primary-100/60 px-4 py-3 text-sm text-ink-700"><span class="font-semibold">Claude's note:</span> {{ $payload['notes'] }}</p>
    @endif
    @if ($pending && ! $canApprove)
        <p class="mb-4 rounded-control bg-warning/10 px-4 py-3 text-sm text-[#8a5f00]">Approving an accommodation sets prices: only super admins and agents can approve it. You can still reject it.</p>
    @endif

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
        <form id="accommodation-approve-form" method="POST" action="{{ route('admin.ai-suggestions.approve-accommodation', $suggestion->id) }}" class="space-y-4" novalidate>
            @csrf
            <x-admin.card title="Building" :subtitle="$editable ? 'Prefilled from the suggestion. Edit anything before approving.' : 'As proposed or applied.'">
                <fieldset class="grid grid-cols-1 gap-4 md:grid-cols-6" @disabled(! $editable)>
                    <x-admin.input name="building[name][en]" label="Name (EN)" :value="$building['name']['en'] ?? null" span="md:col-span-3" required maxlength="160" />
                    <x-admin.input name="building[name][it]" label="Name (IT)" :value="$building['name']['it'] ?? null" span="md:col-span-3" maxlength="160" />
                    <x-admin.input name="building[slug]" label="Slug" :value="$building['slug'] ?? null" span="md:col-span-2" hint="Public URL of the building." />
                    <x-admin.select name="building[type]" label="Type" :options="$buildingTypes" :selected="$building['type'] ?? null" :select2="false" span="md:col-span-2" />
                    <x-admin.select name="building[destination_slug]" label="Destination" :options="$destinations" :selected="$building['destination_slug'] ?? null" placeholder="Choose a destination" span="md:col-span-2" required />
                    <x-admin.select name="building[stars]" label="Star rating" :options="[1 => '1 star', 2 => '2 stars', 3 => '3 stars', 4 => '4 stars', 5 => '5 stars']" :selected="$building['stars'] ?? null" placeholder="No rating" :select2="false" span="md:col-span-2" />
                    <x-admin.input type="time" name="building[check_in_from]" label="Check-in from" :value="$building['check_in_from'] ?? null" span="md:col-span-2" />
                    <x-admin.input type="time" name="building[check_out_until]" label="Check-out until" :value="$building['check_out_until'] ?? null" span="md:col-span-2" />
                    <x-admin.location-picker address-name="building[address]" lat-name="building[lat]" lng-name="building[lng]"
                                             :address="$building['address'] ?? null" :lat="$building['lat'] ?? null" :lng="$building['lng'] ?? null" />
                    <x-admin.input name="building[phone]" label="Phone" :value="$building['phone'] ?? null" span="md:col-span-3" />
                    <x-admin.input type="email" name="building[email]" label="E-mail" :value="$building['email'] ?? null" span="md:col-span-3" />
                    <x-admin.select name="building[amenities][]" label="Amenities" :options="$amenityOptions->all()" :selected="$building['amenities'] ?? []" multiple tags span="md:col-span-6" />
                    <x-admin.textarea name="building[description][en]" label="Description (EN)" :value="$building['description']['en'] ?? null" :rows="4" />
                    <x-admin.textarea name="building[description][it]" label="Description (IT)" :value="$building['description']['it'] ?? null" :rows="4" />
                    <div class="md:col-span-3">
                        <label class="flex items-center gap-2 text-sm font-medium text-ink-900">
                            <input type="hidden" name="building[taxes_included]" value="0">
                            <input type="checkbox" name="building[taxes_included]" value="1" class="size-4 rounded" @checked(old('building.taxes_included', $building['taxes_included'] ?? true))>
                            Taxes included in the price
                        </label>
                    </div>
                    <x-admin.input name="building[tax_per_night]" label="Tax per night (when not included)" :value="$building['tax_per_night'] ?? null" span="md:col-span-3" suffix="€" />
                </fieldset>
            </x-admin.card>

            <x-admin.card :title="'Rooms ('.count($rooms).')'" subtitle="Base price per night for the default group; other groups and seasons can be set in the price matrix after approval.">
                <div data-repeater>
                    <div data-repeater-list class="space-y-3">
                        @foreach ($rooms as $index => $room)
                            @include('admin.ai-suggestions.partials.room-row', ['index' => (string) $index, 'room' => $room])
                        @endforeach
                    </div>
                    <p data-repeater-empty class="rounded-control border border-dashed border-ink-300 p-6 text-center text-sm text-ink-500" @if ($rooms !== []) hidden @endif>No rooms.</p>
                    @if ($editable)
                        <template data-repeater-template>
                            @include('admin.ai-suggestions.partials.room-row', ['index' => '__INDEX__', 'room' => ['max_children' => 0, 'min_nights' => 1]])
                        </template>
                        <button type="button" data-repeater-add class="btn-ghost mt-4"><x-admin.icon name="plus" size="18" /> Add room</button>
                    @endif
                </div>
                <p class="mt-4 flex items-start gap-2 rounded-control bg-primary-100/60 px-4 py-3 text-sm text-ink-700">
                    <x-admin.icon name="alert" size="18" class="mt-0.5 shrink-0" /> Partners pay the customer price for rooms (no commission).
                </p>
            </x-admin.card>
            <input type="hidden" name="notes" value="{{ $payload['notes'] ?? '' }}">
        </form>

        <aside class="space-y-4">
            @if ($pending)
                <x-admin.card title="Validation">
                    <ul class="space-y-2 text-sm">
                        @foreach ($checks as $check)
                            <li class="flex items-start gap-2">
                                <span @class(['mt-0.5 shrink-0', 'text-success' => $check['ok'], 'text-warning' => ! $check['ok']])><x-admin.icon :name="$check['ok'] ? 'check' : 'alert'" size="16" /></span>
                                <span>{{ $check['message'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-admin.card>
                <x-admin.card title="Decision" :subtitle="'Approve creates the building and '.count($rooms).' '.str('room')->plural(count($rooms)).' in one transaction.'">
                    @error('suggestion')<p class="form-error mb-3">{{ $message }}</p>@enderror
                    @if ($canApprove)
                        <label class="mb-3 flex items-center gap-2 text-sm">
                            <input type="checkbox" name="publish" value="1" form="accommodation-approve-form" class="size-4 rounded" @checked(old('publish'))>
                            Publish immediately (otherwise created as a draft)
                        </label>
                        <button type="submit" form="accommodation-approve-form" class="btn-cta w-full">Approve (creates building)</button>
                    @endif
                    <div class="mt-5 border-t border-ink-100 pt-4">
                        @include('admin.ai-suggestions.partials.reject')
                    </div>
                </x-admin.card>
            @else
                @include('admin.ai-suggestions.partials.decided')
            @endif
        </aside>
    </div>
</x-admin.layout>
