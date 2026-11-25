@php
    use App\Enums\PackagePriceMode;
    use App\Enums\PublishStatus;

    $title = $package->exists ? (string) $package->title : 'New package';
    $lines = fn (string $key): array => collect($package->getTranslations($key))->map(fn ($items) => implode("\n", (array) $items))->all();

    $stageRows = old('stages') !== null ? array_values(old('stages')) : $package->stages->map(fn ($stage) => [
        'destination_id' => $stage->destination_id,
        'days' => $stage->days,
        'nights' => $stage->nights,
        'accommodation' => $stage->getTranslations('accommodation'),
        'trips' => $stage->stageTrips->map(fn ($trip) => ['trip_id' => $trip->trip_id, 'day_number' => $trip->day_number])->all(),
    ])->all();
    $transferRows = old('transfers') !== null ? array_values(old('transfers')) : $package->includedTransfers->map(fn ($transfer) => [
        'day_number' => $transfer->day_number, 'time' => $transfer->time, 'from_place' => $transfer->from_place,
        'to_place' => $transfer->to_place, 'vehicle_type' => $transfer->vehicle_type?->value,
    ])->all();

    $tabs = [
        'tab-basics' => 'Basics',
        'tab-programme' => 'Stages and days',
        'tab-transfers' => 'Included transfers',
        'tab-pricing' => 'Pricing',
        'tab-prices' => 'Price matrix',
        'tab-addons' => 'Add-ons',
    ];
@endphp
<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :breadcrumbs="['Catalogue' => null, 'Packages' => route('admin.packages.index'), $title => null]"
                         :subtitle="$package->exists ? $package->days.' days · '.$package->stages->count().' stages · '.$package->status->label() : 'Build the programme from existing trips. The price list is available after the first save.'">
        <x-slot:actions>
            @if ($package->exists)
                @can('manage-prices')<a href="{{ route('admin.packages.room-options', $package) }}" class="btn-ghost">Rooms</a>@endcan
                <form method="POST" action="{{ route('admin.packages.destroy', $package) }}" data-confirm="Delete “{{ $title }}”? This cannot be undone.">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-ghost text-error">Delete</button>
                </form>
            @endif
            <button type="submit" form="package-form" class="btn-cta">{{ $package->exists ? 'Save changes' : 'Create package' }}</button>
        </x-slot:actions>
    </x-admin.page-header>

    <div data-tabs data-tabs-hash>
        <div role="tablist" aria-label="Package builder sections" class="mb-5 flex overflow-x-auto overflow-y-hidden border-b border-ink-300/60">
            @foreach ($tabs as $id => $label)
                <button type="button" role="tab" id="{{ $id }}-tab" aria-controls="{{ $id }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" class="tab-link">{{ $label }}</button>
            @endforeach
        </div>

        <form id="package-form" method="POST" action="{{ $package->exists ? route('admin.packages.update', $package) : route('admin.packages.store') }}" novalidate>
            @csrf
            @if ($package->exists) @method('PUT') @endif
            <input type="hidden" name="_tab" value="tab-basics">

            {{-- Basics --}}
            <div role="tabpanel" id="tab-basics" aria-labelledby="tab-basics-tab" class="space-y-4">
                <x-admin.form-section title="Basics" description="Title, summary and description in English and Italian.">
                    <x-admin.translatable name="title" label="Title" :value="$package->getTranslations('title')" span="md:col-span-4" required-locale="en" />
                    <x-admin.input name="slug" label="Slug" :value="$package->slug" hint="Leave empty to generate from the English title." span="md:col-span-2" />
                    <x-admin.translatable name="summary" label="Summary" type="textarea" :rows="2" :value="$package->getTranslations('summary')" />
                    <x-admin.translatable name="description" label="Description" type="textarea" :rows="5" :value="$package->getTranslations('description')" />
                </x-admin.form-section>

                <x-admin.form-section title="Duration and visibility">
                    <x-admin.select name="destination_id" label="Main destination" :options="$destinations" :selected="$package->destination_id" placeholder="First stage destination" data-s2-clear span="md:col-span-2" />
                    <x-admin.input name="days" label="Days" type="number" min="1" :value="$package->days" span="md:col-span-1" required />
                    <x-admin.input name="nights" label="Nights" type="number" min="0" :value="$package->nights" span="md:col-span-1" />
                    <x-admin.select name="status" label="Status" :options="PublishStatus::options()" :selected="$package->status ?? PublishStatus::Draft" :select2="false" span="md:col-span-2" />
                    <x-admin.input name="cover_image" label="Cover image path" :value="$package->cover_image" placeholder="/images/placeholders/package-1.jpg" span="md:col-span-3" />
                    <x-admin.input name="free_cancellation_hours" label="Free cancellation (h)" type="number" min="0" :value="$package->free_cancellation_hours" span="md:col-span-1" />
                    <x-admin.switch name="featured" label="Featured" description="Shown on the home page" :checked="$package->featured" span="md:col-span-2" />
                </x-admin.form-section>

                <x-admin.form-section title="Highlights, included and excluded" description="One item per line. Transfers are always listed as included.">
                    <x-admin.translatable name="highlights" label="Highlights" type="textarea" :rows="3" :value="$lines('highlights')" span="md:col-span-2" />
                    <x-admin.translatable name="included" label="Included" type="textarea" :rows="3" :value="$lines('included')" span="md:col-span-2" />
                    <x-admin.translatable name="excluded" label="Excluded" type="textarea" :rows="3" :value="$lines('excluded')" span="md:col-span-2" />
                    <x-admin.translatable name="cancellation_policy" label="Cancellation policy" type="textarea" :rows="2" :value="$package->getTranslations('cancellation_policy')" />
                </x-admin.form-section>
            </div>

            {{-- Stages --}}
            <div role="tabpanel" id="tab-programme" aria-labelledby="tab-programme-tab" hidden>
                <x-admin.card title="Stages" subtitle="Consecutive stages; their days must add up to the package days. Place existing trips on the days of each stage (more than one per day is allowed).">
                    <div data-repeater data-placeholder="__STAGE__" data-sortable>
                        <div data-repeater-list class="space-y-3">
                            @foreach ($stageRows as $stageIndex => $stage)
                                @include('admin.packages.partials.stage-row', ['stageIndex' => (string) $stageIndex, 'stage' => $stage])
                            @endforeach
                        </div>
                        <p data-repeater-empty class="rounded-control border border-dashed border-ink-300 p-6 text-center text-sm text-ink-500" @if ($stageRows !== []) hidden @endif>No stages yet.</p>
                        @error('stages')<p class="form-error mt-2">{{ $message }}</p>@enderror
                        <template data-repeater-template>
                            @include('admin.packages.partials.stage-row', ['stageIndex' => '__STAGE__', 'stage' => []])
                        </template>
                        <button type="button" data-repeater-add class="btn-primary mt-4"><x-admin.icon name="plus" size="18" /> Add stage</button>
                    </div>
                </x-admin.card>
            </div>

            {{-- Included transfers --}}
            <div role="tabpanel" id="tab-transfers" aria-labelledby="tab-transfers-tab" hidden>
                <x-admin.card title="Included transfers" subtitle="Airport pick-up, drop-off and transfers between stages. Never charged: each booking creates one task per row (day = package day).">
                    <div data-repeater>
                        <div data-repeater-list>
                            @foreach ($transferRows as $index => $row)
                                @include('admin.packages.partials.transfer-row', ['index' => (string) $index, 'row' => $row])
                            @endforeach
                        </div>
                        <p data-repeater-empty class="py-4 text-sm text-ink-500" @if ($transferRows !== []) hidden @endif>No included transfers.</p>
                        <template data-repeater-template>
                            @include('admin.packages.partials.transfer-row', ['index' => '__INDEX__', 'row' => []])
                        </template>
                        <button type="button" data-repeater-add class="btn-primary mt-4"><x-admin.icon name="plus" size="18" /> Add transfer</button>
                    </div>
                </x-admin.card>
            </div>

            {{-- Pricing --}}
            <div role="tabpanel" id="tab-pricing" aria-labelledby="tab-pricing-tab" class="space-y-4" hidden>
                <x-admin.form-section title="Booking types and party" description="Packages are booked per person.">
                    <x-admin.switch name="private_enabled" label="Private bookings" :checked="$package->private_enabled" span="md:col-span-2" />
                    <x-admin.switch name="group_enabled" label="Group bookings" :checked="$package->group_enabled" span="md:col-span-2" />
                    <div class="md:col-span-2"></div>
                    <x-admin.input name="min_persons" label="Minimum persons" type="number" min="1" :value="$package->min_persons" span="md:col-span-2" />
                    <x-admin.input name="max_persons" label="Maximum persons" type="number" min="1" :value="$package->max_persons" span="md:col-span-2" />
                    <x-admin.input name="deposit_percent" label="Deposit" type="number" step="0.01" min="0" max="100" suffix="%" :value="$package->deposit_percent" span="md:col-span-2" />
                </x-admin.form-section>

                <x-admin.form-section title="Price mode" description="A fixed price per person (per nationality group and season in the Price matrix tab), or computed from the linked trips minus a manual discount.">
                    <x-admin.segmented name="price_mode" :selected="$package->price_mode ?? PackagePriceMode::Fixed" :options="[
                        'fixed' => ['Fixed price', 'Adult and child price per person'],
                        'computed' => ['Computed from trips', 'Sum of the trips on their dates, minus a discount'],
                    ]" />
                    <div data-show-when="[name=price_mode]=fixed" class="grid grid-cols-1 gap-5 md:col-span-6 md:grid-cols-6">
                        <x-admin.money name="base_price" label="Adult price" :value="$package->base_price" hint="Product base price" span="md:col-span-2" />
                        <x-admin.money name="base_child_price" label="Child price" :value="$package->base_child_price" span="md:col-span-2" />
                    </div>
                    <div data-show-when="[name=price_mode]=computed" class="grid grid-cols-1 gap-5 md:col-span-6 md:grid-cols-6">
                        <x-admin.input name="discount_percent" label="Discount on the trips" type="number" step="0.01" min="0" max="100" suffix="%" :value="$package->discount_percent" span="md:col-span-2" />
                    </div>
                </x-admin.form-section>
            </div>

            {{-- Add-ons --}}
            <div role="tabpanel" id="tab-addons" aria-labelledby="tab-addons-tab" hidden>
                <x-admin.form-section title="Add-ons" description="Reusable add-ons from the library, offered in the booking widget.">
                    <x-admin.select name="addon_ids[]" label="Attached add-ons" :options="$addons" :selected="$package->addons->pluck('id')->all()" multiple placeholder="Search add-ons" span="md:col-span-6" />
                </x-admin.form-section>
            </div>
        </form>

        {{-- Price matrix: separate form (PriceMatrixService) --}}
        <div role="tabpanel" id="tab-prices" aria-labelledby="tab-prices-tab" hidden>
            @if (! $package->exists)
                <x-admin.card><p class="text-sm text-ink-700">Save the package first, then set its prices per nationality group and season.</p></x-admin.card>
            @elseif ($package->price_mode === PackagePriceMode::Computed)
                <x-admin.card><p class="text-sm text-ink-700">This package is priced from its trips: each trip's own price matrix applies (for the customer's nationality group) on the date of its day.</p></x-admin.card>
            @else
                @include('admin.partials.price-matrix', ['action' => route('admin.packages.seasons.update', $package), 'matrix' => $matrix])
            @endif
        </div>
    </div>
</x-admin.layout>
