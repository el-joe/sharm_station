@php
    $title = $hub->exists ? (string) $hub->name : 'New hub';
    $tiers = old('distance_tiers') !== null ? array_values(old('distance_tiers')) : ($hub->distance_tiers ?? []);
    $extras = old('extras') !== null ? array_values(old('extras')) : ($hub->extras ?? []);
@endphp
<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :breadcrumbs="['Transfers' => null, 'Hubs' => route('admin.transfers.hubs.index'), $title => null]" subtitle="P = max(M, (B + Σ dᵢ·Rᵢ + W + S) · V) + F">
        <x-slot:actions><button type="submit" form="hub-form" class="btn-cta">Save</button></x-slot:actions>
    </x-admin.page-header>
    <form id="hub-form" method="POST" action="{{ $hub->exists ? route('admin.transfers.hubs.update', $hub) : route('admin.transfers.hubs.store') }}" novalidate class="space-y-4">
        @csrf
        @if ($hub->exists) @method('PUT') @endif
        <x-admin.form-section title="Hub and service area">
            <x-admin.translatable name="name" label="Name" :value="$hub->getTranslations('name')" required-locale="en" span="md:col-span-4" />
            <x-admin.input name="slug" label="Slug" :value="$hub->slug" span="md:col-span-2" />
            <x-admin.select name="type" label="Type" :options="\App\Enums\HubType::options()" :selected="$hub->type" :select2="false" span="md:col-span-2" />
            <x-admin.select name="destination_id" label="Destination" :options="$destinations" :selected="$hub->destination_id" placeholder="None" data-s2-clear span="md:col-span-2" />
            <x-admin.input name="sort" label="Order" type="number" min="0" :value="$hub->sort ?? 0" span="md:col-span-1" />
            <x-admin.switch name="is_enabled" label="Enabled" :checked="$hub->is_enabled" span="md:col-span-1" />
            <x-admin.input name="radius_km" label="Service radius (km)" type="number" step="0.1" :value="$hub->radius_km" span="md:col-span-1" />
            <x-admin.input name="max_distance_km" label="Max ride (km)" type="number" step="0.1" :value="$hub->max_distance_km" span="md:col-span-1" />
            <x-admin.select name="vehicle_type_ids[]" label="Vehicles available" :options="$vehicles" :selected="$hub->vehicle_type_ids ?? []" multiple placeholder="All vehicles" span="md:col-span-6" />
            <x-admin.location-picker :lat="$hub->lat" :lng="$hub->lng" search-label="Search the hub (airport, port, hotel…)" />
        </x-admin.form-section>

        <x-admin.form-section title="Fares" description="EUR. Waiting is charged after the grace period; the night surcharge applies from 22:00 to 06:00; fees (airport, tolls) are added after the minimum.">
            <x-admin.money name="base_fare" label="Base fare B" :value="$hub->base_fare ?? 0" span="md:col-span-2" />
            <x-admin.money name="min_fare" label="Minimum fare M" :value="$hub->min_fare ?? 0" span="md:col-span-2" />
            <x-admin.money name="fixed_fees" label="Fixed fees F" :value="$hub->fixed_fees ?? 0" span="md:col-span-2" />
            <x-admin.input name="waiting_grace_minutes" label="Waiting grace (min)" type="number" min="0" :value="$hub->waiting_grace_minutes" span="md:col-span-2" />
            <x-admin.money name="waiting_rate_per_hour" label="Waiting rate per hour" :value="$hub->waiting_rate_per_hour ?? 0" span="md:col-span-2" />
            <x-admin.money name="night_surcharge" label="Night surcharge S" :value="$hub->night_surcharge ?? 0" span="md:col-span-2" />
            <x-admin.input name="return_discount_percent" label="Return discount" type="number" step="0.01" min="0" max="100" suffix="%" :value="$hub->return_discount_percent ?? 0" span="md:col-span-2" />
            <x-admin.input name="deposit_percent" label="Deposit (empty = full payment)" type="number" step="0.01" min="0" max="100" suffix="%" :value="$hub->deposit_percent" span="md:col-span-2" />
            <x-admin.input name="min_notice_hours" label="Minimum notice (h)" type="number" min="0" :value="$hub->min_notice_hours" span="md:col-span-2" />
        </x-admin.form-section>

        <x-admin.card title="Distance tiers" subtitle="Price per km R by tier, in ascending order. Leave the last limit empty for “beyond”.">
            <div data-repeater>
                <div data-repeater-list>
                    @foreach ($tiers as $index => $tier)
                        <div data-repeater-row class="grid grid-cols-[1fr_1fr_40px] items-end gap-3 border-b border-ink-100 py-2">
                            <div><label class="form-label text-xs">Up to km</label><input type="number" step="0.1" name="distance_tiers[{{ $index }}][up_to_km]" value="{{ $tier['up_to_km'] ?? '' }}" placeholder="Beyond" class="form-control"></div>
                            <div><label class="form-label text-xs">€ per km</label><input type="number" step="0.01" min="0" name="distance_tiers[{{ $index }}][rate]" value="{{ $tier['rate'] ?? '' }}" class="form-control"></div>
                            <button type="button" data-repeater-remove class="rounded-control p-2 text-ink-500 hover:text-error" aria-label="Remove tier"><x-admin.icon name="trash" size="18" /></button>
                        </div>
                    @endforeach
                </div>
                @error('distance_tiers')<p class="form-error">{{ $message }}</p>@enderror
                <template data-repeater-template>
                    <div data-repeater-row class="grid grid-cols-[1fr_1fr_40px] items-end gap-3 border-b border-ink-100 py-2">
                        <div><label class="form-label text-xs">Up to km</label><input type="number" step="0.1" name="distance_tiers[__INDEX__][up_to_km]" placeholder="Beyond" class="form-control"></div>
                        <div><label class="form-label text-xs">€ per km</label><input type="number" step="0.01" min="0" name="distance_tiers[__INDEX__][rate]" class="form-control"></div>
                        <button type="button" data-repeater-remove class="rounded-control p-2 text-ink-500 hover:text-error" aria-label="Remove tier"><x-admin.icon name="trash" size="18" /></button>
                    </div>
                </template>
                <button type="button" data-repeater-add class="btn-primary mt-3"><x-admin.icon name="plus" size="18" /> Add tier</button>
            </div>
        </x-admin.card>

        <x-admin.card title="Extras" subtitle="Optional extras the customer can add (child seat, extra stop, name sign).">
            <div data-repeater>
                <div data-repeater-list>
                    @foreach ($extras as $index => $extra)
                        <div data-repeater-row class="grid grid-cols-[1fr_2fr_1fr_40px] items-end gap-3 border-b border-ink-100 py-2">
                            <div><label class="form-label text-xs">Code</label><input type="text" name="extras[{{ $index }}][code]" value="{{ $extra['code'] ?? '' }}" class="form-control"></div>
                            <div><label class="form-label text-xs">Label</label><input type="text" name="extras[{{ $index }}][label]" value="{{ $extra['label'] ?? '' }}" class="form-control"></div>
                            <div><label class="form-label text-xs">Price €</label><input type="number" step="0.01" min="0" name="extras[{{ $index }}][price]" value="{{ $extra['price'] ?? '' }}" class="form-control"></div>
                            <button type="button" data-repeater-remove class="rounded-control p-2 text-ink-500 hover:text-error" aria-label="Remove extra"><x-admin.icon name="trash" size="18" /></button>
                        </div>
                    @endforeach
                </div>
                <template data-repeater-template>
                    <div data-repeater-row class="grid grid-cols-[1fr_2fr_1fr_40px] items-end gap-3 border-b border-ink-100 py-2">
                        <div><label class="form-label text-xs">Code</label><input type="text" name="extras[__INDEX__][code]" class="form-control"></div>
                        <div><label class="form-label text-xs">Label</label><input type="text" name="extras[__INDEX__][label]" class="form-control"></div>
                        <div><label class="form-label text-xs">Price €</label><input type="number" step="0.01" min="0" name="extras[__INDEX__][price]" class="form-control"></div>
                        <button type="button" data-repeater-remove class="rounded-control p-2 text-ink-500 hover:text-error" aria-label="Remove extra"><x-admin.icon name="trash" size="18" /></button>
                    </div>
                </template>
                <button type="button" data-repeater-add class="btn-primary mt-3"><x-admin.icon name="plus" size="18" /> Add extra</button>
            </div>
        </x-admin.card>
    </form>
</x-admin.layout>
