@php
    use App\Enums\BookingType;
    use App\Enums\PricingMode;
    use App\Enums\PublishStatus;
    use App\Enums\TripType;

    $isNile = $trip->type === TripType::Nile || old('type') === TripType::Nile->value;
    $section = $isNile ? 'Nile trips' : 'Trips';
    $indexUrl = route('admin.trips.index', $isNile ? ['type' => 'nile'] : []);
    $title = $trip->exists ? (string) $trip->title : ($isNile ? 'New Nile trip' : 'New trip');

    $unitRows = old('unit_options') !== null ? array_values(old('unit_options')) : $trip->unitOptions->map(fn ($option) => [
        'id' => $option->id, 'name' => $option->getTranslations('name'), 'seats' => $option->seats, 'price' => $option->price,
        'max_quantity' => $option->max_quantity, 'children_allowed' => $option->children_allowed, 'sort' => $option->sort,
    ])->all();
    $tierRows = old('private_tiers') !== null ? array_values(old('private_tiers')) : $trip->privateTiers->map(fn ($tier) => $tier->only(['id', 'min_people', 'max_people', 'price_per_person']))->all();
    $dayRows = old('itinerary') !== null ? array_values(old('itinerary')) : $trip->tripDays->map(fn ($day) => [
        'title' => $day->getTranslations('title'), 'description' => $day->getTranslations('description'), 'overnight' => $day->overnight,
        'items' => $day->items->map(fn ($item) => [
            'time_from' => $item->time_from, 'time_to' => $item->time_to, 'title' => $item->getTranslations('title'),
            'description' => $item->getTranslations('description'), 'place' => $item->place,
        ])->all(),
    ])->all();
    $rules = $trip->availabilityRules->keyBy(fn ($rule) => $rule->booking_type->value);
    $privateRule = $rules->get(BookingType::Private->value);
    $groupRule = $rules->get(BookingType::Group->value);
    $weekdayNames = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
    $groupWeekdays = array_map('intval', old('availability.group.weekdays', $groupRule?->weekdays ?? []));

    $tabs = array_filter([
        'tab-basics' => 'Basics',
        'tab-pricing' => 'Pricing',
        'tab-prices' => 'Price matrix',
        'tab-availability' => 'Availability',
        'tab-itinerary' => $isNile || $trip->type === TripType::MultiDay ? 'Itinerary' : 'Timeline',
        'tab-addons' => 'Add-ons',
        'tab-nile' => $isNile ? 'Nile' : null,
        'tab-seo' => 'SEO',
    ]);
@endphp
<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :breadcrumbs="['Catalogue' => null, $section => $indexUrl, $title => null]"
                         :subtitle="$trip->exists ? $trip->type->label().' · '.($trip->destination?->name ?? '').' · '.$trip->status->label() : 'Fill in the basics, then pricing. The price list is available after the first save.'">
        <x-slot:actions>
            @if ($trip->exists && $trip->type !== \App\Enums\TripType::SingleDay)
                @can('manage-prices')<a href="{{ route('admin.trips.room-options', $trip) }}" class="btn-ghost">{{ $trip->type === \App\Enums\TripType::Nile ? 'Cabins' : 'Rooms' }}</a>@endcan
            @endif
            @if ($trip->exists)
                <form method="POST" action="{{ route('admin.trips.destroy', $trip) }}" data-confirm="Delete “{{ $title }}”? This cannot be undone.">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-ghost text-error">Delete</button>
                </form>
            @endif
            <button type="submit" form="trip-form" class="btn-cta">{{ $trip->exists ? 'Save changes' : 'Create trip' }}</button>
        </x-slot:actions>
    </x-admin.page-header>

    <div data-tabs data-tabs-hash>
        <div role="tablist" aria-label="Trip editor sections" class="mb-5 flex overflow-x-auto overflow-y-hidden border-b border-ink-300/60">
            @foreach ($tabs as $id => $label)
                <button type="button" role="tab" id="{{ $id }}-tab" aria-controls="{{ $id }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" class="tab-link">{{ $label }}</button>
            @endforeach
        </div>

        <form id="trip-form" method="POST" action="{{ $trip->exists ? route('admin.trips.update', $trip) : route('admin.trips.store') }}" novalidate>
            @csrf
            @if ($trip->exists) @method('PUT') @endif
            <input type="hidden" name="_tab" value="tab-basics">
            <input type="hidden" name="itinerary_present" value="1">
            <input type="hidden" name="addons_present" value="1">

            {{-- Basics --}}
            <div role="tabpanel" id="tab-basics" aria-labelledby="tab-basics-tab" class="space-y-4">
                <x-admin.form-section title="Basics" description="Title, summary and description in English and Italian.">
                    <x-admin.translatable name="title" label="Title" :value="$trip->getTranslations('title')" span="md:col-span-4" required-locale="en" />
                    <x-admin.input name="slug" label="Slug" :value="$trip->slug" hint="Leave empty to generate from the English title." span="md:col-span-2" />
                    <x-admin.translatable name="summary" label="Summary" type="textarea" :rows="2" :value="$trip->getTranslations('summary')" />
                    <x-admin.translatable name="description" label="Description" type="textarea" :rows="6" :value="$trip->getTranslations('description')" />
                </x-admin.form-section>

                <x-admin.form-section title="Classification">
                    <x-admin.select name="destination_id" label="Destination" :options="$destinations" :selected="$trip->destination_id" placeholder="Choose a destination" required span="md:col-span-2" />
                    @if ($isNile)
                        <input type="hidden" name="type" value="{{ TripType::Nile->value }}">
                    @else
                        <x-admin.select name="type" label="Type" :options="collect(TripType::options())->except('nile')->all()" :selected="$trip->type" :select2="false" span="md:col-span-2" />
                    @endif
                    <x-admin.select name="status" label="Status" :options="PublishStatus::options()" :selected="$trip->status ?? PublishStatus::Draft" :select2="false" span="md:col-span-2" />
                    <x-admin.input name="category" label="Category" :value="$trip->category" hint="e.g. snorkelling, desert, culture" span="md:col-span-2" />
                    <x-admin.input name="days" label="Days" type="number" min="1" :value="$trip->days ?? 1" span="md:col-span-1" />
                    <x-admin.input name="nights" label="Nights" type="number" min="0" :value="$trip->nights ?? 0" span="md:col-span-1" />
                    <x-admin.input name="duration_hours" label="Duration (hours)" type="number" step="0.5" min="0" :value="$trip->duration_hours" span="md:col-span-1" />
                    <x-admin.input name="free_cancellation_hours" label="Free cancellation (h)" type="number" min="0" :value="$trip->free_cancellation_hours" span="md:col-span-1" />
                </x-admin.form-section>

                <x-admin.form-section title="Meeting point" description="Where customers meet the guide or are picked up. Shown on the trip page and in the booking confirmation.">
                    <x-admin.translatable name="meeting_point" label="Meeting point" :value="$trip->getTranslations('meeting_point')" span="md:col-span-6" />
                    <x-admin.location-picker :lat="$trip->lat" :lng="$trip->lng" lat-label="Meeting point latitude" lng-label="Meeting point longitude" search-label="Search the meeting point" />
                </x-admin.form-section>

                @php
                    $faq = (array) ($trip->getTranslations('faq') ?: []);
                    $faqRows = old('faq_rows') ?? collect(range(0, max(count($faq['en'] ?? []), count($faq['it'] ?? [])) - 1))
                        ->filter(fn ($i) => $i >= 0)
                        ->map(fn ($i) => [
                            'question' => ['en' => $faq['en'][$i]['question'] ?? '', 'it' => $faq['it'][$i]['question'] ?? ''],
                            'answer' => ['en' => $faq['en'][$i]['answer'] ?? '', 'it' => $faq['it'][$i]['answer'] ?? ''],
                        ])->values()->all();
                    $bring = collect($trip->getTranslations('what_to_bring') ?: [])->map(fn ($items) => implode("\n", (array) $items))->all();
                    $languages = (array) trans('catalog.languages', [], 'en');
                @endphp
                <x-admin.form-section title="Trip details" description="Shown on the trip page: unit label, what to bring, guide languages, cancellation text and FAQ.">
                    <x-admin.translatable name="unit_label" label="Unit label (per-unit trips)" :value="$trip->getTranslations('unit_label')" span="md:col-span-2" />
                    <div data-field class="md:col-span-4">
                        <input type="hidden" name="guide_languages_present" value="1">
                        <x-admin.select name="guide_languages[]" label="Guide languages" :options="$languages" :selected="old('guide_languages', $trip->guide_languages ?? [])" multiple data-s2-tags placeholder="Choose languages" span="" />
                    </div>
                    <x-admin.translatable name="what_to_bring_lines" label="What to bring (one item per line)" type="textarea" :rows="4" :value="$bring" span="md:col-span-3" />
                    <x-admin.translatable name="cancellation_policy" label="Cancellation policy" type="textarea" :rows="4" :value="$trip->getTranslations('cancellation_policy')" span="md:col-span-3" />
                    <div class="md:col-span-6">
                        <p class="form-label">FAQ</p>
                        <input type="hidden" name="faq_present" value="1">
                        <div data-repeater data-sortable>
                            <div data-repeater-list>
                                @foreach ($faqRows as $index => $row)
                                    @include('admin.trips.partials.faq-row', ['index' => (string) $index, 'row' => $row])
                                @endforeach
                            </div>
                            <p data-repeater-empty class="py-3 text-sm text-ink-500" @if ($faqRows !== []) hidden @endif>No questions yet.</p>
                            <template data-repeater-template>
                                @include('admin.trips.partials.faq-row', ['index' => '__INDEX__', 'row' => []])
                            </template>
                            <button type="button" data-repeater-add class="btn-ghost btn-sm mt-3">Add question</button>
                            @if ($errors->has('faq') || $errors->has('faq.*'))<p class="form-error">Every question needs both the question and the answer in that language.</p>@endif
                        </div>
                    </div>
                </x-admin.form-section>

                <x-admin.form-section title="Booking types and visibility">
                    <x-admin.switch name="private_enabled" label="Private bookings" description="Own date, private tiers per person" :checked="$trip->private_enabled" span="md:col-span-2" />
                    <x-admin.switch name="group_enabled" label="Group bookings" description="Scheduled departures, adult/child prices" :checked="$trip->group_enabled" span="md:col-span-2" />
                    <x-admin.switch name="featured" label="Featured" description="Shown on the home page" :checked="$trip->featured" span="md:col-span-2" />
                </x-admin.form-section>
            </div>

            {{-- Pricing --}}
            <div role="tabpanel" id="tab-pricing" aria-labelledby="tab-pricing-tab" class="space-y-4" hidden>
                @if ($canPrice)
                    <x-admin.form-section title="Pricing mode" description="How customers are charged for this trip (SRS 3.4.1).">
                        <x-admin.segmented name="pricing_mode" :selected="$trip->pricing_mode ?? PricingMode::PerPerson" :options="[
                            'per_person' => ['Per person', 'Same price per traveller, with a max persons limit'],
                            'per_unit' => ['Per unit', 'Customers book units, e.g. Single or Double buggy'],
                        ]" />
                        <x-admin.input name="min_persons" label="Minimum persons" type="number" min="1" :value="$trip->min_persons ?? 1" span="md:col-span-2" />
                        <x-admin.input name="max_persons" label="Maximum persons per booking" type="number" min="1" :value="$trip->max_persons" hint="Applies to the whole booking" span="md:col-span-2" />
                        <x-admin.input name="deposit_percent" label="Deposit" type="number" step="0.01" min="0" max="100" suffix="%" :value="$trip->deposit_percent" span="md:col-span-2" />
                    </x-admin.form-section>

                    <div data-show-when="[name=pricing_mode]=per_unit">
                        <x-admin.card title="Unit options" subtitle="Each unit has its own seats and product base price. Prices per nationality group and season are set in the Price matrix tab.">
                            <div data-repeater data-sortable>
                                <div class="hidden grid-cols-[24px_minmax(0,2.2fr)_70px_minmax(0,1fr)_80px_64px_40px] gap-2 rounded-control bg-sand-50 px-0 py-2.5 text-xs font-semibold text-ink-700 md:grid">
                                    <span></span><span>Option name (EN / IT)</span><span>Seats</span><span>Base price (EUR)</span><span>Max qty</span><span class="text-center">Children</span><span></span>
                                </div>
                                <div data-repeater-list>
                                    @foreach ($unitRows as $index => $row)
                                        @include('admin.trips.partials.unit-option-row', ['index' => (string) $index, 'row' => $row])
                                    @endforeach
                                </div>
                                <p data-repeater-empty class="py-4 text-sm text-ink-500" @if ($unitRows !== []) hidden @endif>No unit options yet.</p>
                                @error('unit_options')<p class="form-error">{{ $message }}</p>@enderror
                                <template data-repeater-template>
                                    @include('admin.trips.partials.unit-option-row', ['index' => '__INDEX__', 'row' => []])
                                </template>
                                <button type="button" data-repeater-add class="btn-primary mt-4"><x-admin.icon name="plus" size="18" /> Add unit option</button>
                            </div>
                        </x-admin.card>
                    </div>

                    <div data-show-when="[name=pricing_mode]=per_person" class="space-y-4">
                        <x-admin.card title="Private tiers" subtitle="Price per person by party size (private bookings).">
                            <div data-repeater>
                                <div data-repeater-list>
                                    @foreach ($tierRows as $index => $row)
                                        @include('admin.trips.partials.tier-row', ['index' => (string) $index, 'row' => $row])
                                    @endforeach
                                </div>
                                <p data-repeater-empty class="py-4 text-sm text-ink-500" @if ($tierRows !== []) hidden @endif>No private tiers.</p>
                                @error('private_tiers')<p class="form-error">{{ $message }}</p>@enderror
                                <template data-repeater-template>
                                    @include('admin.trips.partials.tier-row', ['index' => '__INDEX__', 'row' => []])
                                </template>
                                <button type="button" data-repeater-add class="btn-primary mt-4"><x-admin.icon name="plus" size="18" /> Add tier</button>
                            </div>
                        </x-admin.card>

                        <x-admin.form-section title="Group prices" description="Product base price, used when neither a season nor the nationality group's base price (Price matrix tab) is set. Child age bands come from the children policy.">
                            <x-admin.money name="base_price" label="Adult price" :value="$trip->base_price" span="md:col-span-2" />
                            <x-admin.money name="base_child_price" label="Child price" :value="$trip->base_child_price" span="md:col-span-2" />
                        </x-admin.form-section>
                    </div>
                @else
                    <x-admin.card>
                        <p class="text-sm text-ink-700">Prices are managed by agents and super admins. Current mode: <strong>{{ ($trip->pricing_mode ?? PricingMode::PerPerson)->label() }}</strong>.</p>
                    </x-admin.card>
                @endif
            </div>

            {{-- Availability --}}
            <div role="tabpanel" id="tab-availability" aria-labelledby="tab-availability-tab" class="space-y-4" hidden>
                <x-admin.form-section title="Private bookings" description="Customers choose any date after the lead time, except blackout dates.">
                    <x-admin.input name="availability[private][lead_time_hours]" label="Lead time (hours)" type="number" min="0" :value="$privateRule?->lead_time_hours ?? 24" span="md:col-span-2" />
                    <x-admin.input name="availability[private][blackout_dates]" label="Blackout dates" data-datepicker="multiple" :value="implode(', ', $privateRule?->blackout_dates ?? [])" placeholder="Pick dates" span="md:col-span-4" />
                </x-admin.form-section>

                <x-admin.form-section title="Group departures" description="Weekly departure days and/or specific dates.">
                    <fieldset class="md:col-span-6">
                        <legend class="form-label">Departure weekdays</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($weekdayNames as $number => $dayName)
                                <label class="cursor-pointer">
                                    <input type="checkbox" name="availability[group][weekdays][]" value="{{ $number }}" class="peer sr-only" @checked(in_array($number, $groupWeekdays, true))>
                                    <span class="inline-flex min-h-10 min-w-12 items-center justify-center rounded-pill border border-ink-300 px-3 text-sm peer-checked:border-primary-700 peer-checked:bg-primary-700 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500">{{ $dayName }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <x-admin.input name="availability[group][dates]" label="Extra departure dates" data-datepicker="multiple" :value="implode(', ', $groupRule?->dates ?? [])" placeholder="Pick dates" span="md:col-span-3" />
                    <x-admin.input name="availability[group][blackout_dates]" label="Blackout dates" data-datepicker="multiple" :value="implode(', ', $groupRule?->blackout_dates ?? [])" placeholder="Pick dates" span="md:col-span-3" />
                    <x-admin.input name="availability[group][lead_time_hours]" label="Lead time (hours)" type="number" min="0" :value="$groupRule?->lead_time_hours ?? 24" span="md:col-span-2" />
                </x-admin.form-section>
            </div>

            {{-- Itinerary / timeline --}}
            <div role="tabpanel" id="tab-itinerary" aria-labelledby="tab-itinerary-tab" hidden>
                <x-admin.card title="{{ $tabs['tab-itinerary'] }}" subtitle="Drag days and items to reorder. Day numbers follow the order.">
                    <div data-itinerary>
                        <div data-repeater data-placeholder="__DAY__" data-sortable>
                            <div data-repeater-list class="space-y-3">
                                @foreach ($dayRows as $dayIndex => $day)
                                    @include('admin.trips.partials.day-row', ['dayIndex' => (string) $dayIndex, 'day' => $day])
                                @endforeach
                            </div>
                            <p data-repeater-empty class="rounded-control border border-dashed border-ink-300 p-6 text-center text-sm text-ink-500" @if ($dayRows !== []) hidden @endif>No itinerary yet.</p>
                            <template data-repeater-template>
                                @include('admin.trips.partials.day-row', ['dayIndex' => '__DAY__', 'day' => []])
                            </template>
                            <button type="button" data-repeater-add class="btn-primary mt-4"><x-admin.icon name="plus" size="18" /> Add day</button>
                        </div>
                    </div>
                </x-admin.card>
            </div>

            {{-- Add-ons --}}
            <div role="tabpanel" id="tab-addons" aria-labelledby="tab-addons-tab" hidden>
                <x-admin.form-section title="Add-ons" description="Reusable add-ons from the library, offered in the booking widget.">
                    <x-admin.select name="addon_ids[]" label="Attached add-ons" :options="$addons" :selected="$trip->addons->pluck('id')->all()" multiple placeholder="Search add-ons" span="md:col-span-6" />
                    <p class="text-xs text-ink-500 md:col-span-6">Manage the library in <a href="{{ route('admin.addons.index') }}" class="font-semibold text-primary-700 hover:underline">Catalogue › Add-ons</a>.</p>
                </x-admin.form-section>
            </div>

            {{-- Nile --}}
            @if ($isNile)
                <div role="tabpanel" id="tab-nile" aria-labelledby="tab-nile-tab" hidden>
                    <x-admin.form-section title="Vessel and route" description="Cabins are sold as rooms; there is no cabin deck selection.">
                        <x-admin.input name="vessel_name" label="Vessel name" :value="$trip->vessel_name" span="md:col-span-2" />
                        <x-admin.input name="itinerary_from" label="Itinerary from" :value="$trip->itinerary_from" placeholder="Luxor" span="md:col-span-2" />
                        <x-admin.input name="itinerary_to" label="Itinerary to" :value="$trip->itinerary_to" placeholder="Aswan" span="md:col-span-2" />
                        <x-admin.select name="vessel_facilities[]" label="Facilities" :options="['pool' => 'Pool', 'restaurant' => 'Restaurant', 'sun_terrace' => 'Sun terrace', 'wifi' => 'Wi-Fi', 'bar' => 'Bar', 'spa' => 'Spa', 'gym' => 'Gym', 'shade' => 'Shade', 'cushions' => 'Cushions']"
                                        :selected="$trip->vessel_facilities ?? []" multiple tags placeholder="Add facilities" span="md:col-span-4" />
                        <x-admin.select name="meal_type" label="Meal type" :options="['full_board' => 'Full board', 'half_board' => 'Half board', 'breakfast' => 'Breakfast', 'tea' => 'Tea and snacks', 'none' => 'No meals']"
                                        :selected="$trip->meal_type" placeholder="Choose" :select2="false" span="md:col-span-2" />
                    </x-admin.form-section>
                </div>
            @endif

            {{-- SEO --}}
            <div role="tabpanel" id="tab-seo" aria-labelledby="tab-seo-tab" hidden>
                <x-admin.form-section title="SEO" description="Search result title and description. Defaults to the trip title and summary.">
                    <x-admin.input name="seo[title]" label="Meta title" :value="$trip->seo['title'] ?? null" maxlength="70" span="md:col-span-6" />
                    <x-admin.textarea name="seo[description]" label="Meta description" :value="$trip->seo['description'] ?? null" :rows="3" maxlength="170" />
                </x-admin.form-section>
            </div>
        </form>

        {{-- Price matrix: separate form (PriceMatrixService) --}}
        <div role="tabpanel" id="tab-prices" aria-labelledby="tab-prices-tab" hidden>
            @if (! $trip->exists)
                <x-admin.card><p class="text-sm text-ink-700">Save the trip first, then set its prices per nationality group and season.</p></x-admin.card>
            @elseif (! $canPrice)
                <x-admin.card><p class="text-sm text-ink-700">The price matrix is managed by agents and super admins.</p></x-admin.card>
            @elseif ($matrix === null || $matrix['fields'] === [])
                <x-admin.card><p class="text-sm text-ink-700">Add {{ $trip->pricing_mode === PricingMode::PerUnit ? 'unit options' : 'private tiers or enable group bookings' }} in the Pricing tab and save, then set the price matrix here.</p></x-admin.card>
            @else
                @include('admin.partials.price-matrix', ['action' => route('admin.trips.seasons.update', $trip), 'matrix' => $matrix])
            @endif
        </div>
    </div>
</x-admin.layout>
