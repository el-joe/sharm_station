@php
    $title = 'Change '.$booking->reference;
    $currentRooms = $isStay
        ? $booking->rooms->map(fn ($room) => ['room_id' => $room->room_id, 'adults' => $room->occupants() > 0 ? $room->adults : $booking->adults, 'children_ages' => $room->occupants() > 0 ? ($room->child_ages ?? []) : $booking->travelers->where('type', \App\Enums\TravelerType::Child)->pluck('age')->filter()->values()->all(), 'extra_beds' => $room->extra_beds, 'cots' => $room->cots])->values()
        : $booking->rooms->whereNotNull('room_option_id')->map(fn ($room) => ['room_option_id' => $room->room_option_id, 'adults' => $room->adults, 'children_ages' => $room->child_ages ?? []])->values();
    $childAges = $booking->travelers->where('type', \App\Enums\TravelerType::Child)->pluck('age')->filter(fn ($age) => $age !== null)->implode(', ');
    $unitQuantities = $booking->units->pluck('quantity', 'trip_unit_option_id');
    $addonQuantities = $booking->addons->pluck('quantity', 'addon_id');
@endphp
<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :breadcrumbs="['Sales' => null, 'Bookings' => route('admin.bookings.index'), $booking->reference => route('admin.bookings.show', $booking), 'Change' => null]"
                         :subtitle="$booking->service_type->label().' · '.$booking->adults.' adults, '.$booking->children.' children'.($booking->partner ? ' · partner '.$booking->partner->company : '')" />

    <form data-booking-editor data-mode="edit" novalidate
          data-quote-url="{{ route('admin.bookings.preview', $booking) }}" data-submit-url="{{ route('admin.bookings.update', $booking) }}" data-submit-method="PUT"
          data-product-type="{{ $isStay ? 'room' : 'trip' }}"
          data-building-rooms='@json($buildingRooms)' data-room-option-list='@json($roomOptions)' data-current-rows='@json($currentRooms)'
          class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_440px]">
        <div class="space-y-4">
            @if ($isStay)
                <x-admin.form-section title="Stay" description="The party stays the same; rooms must be in the same building. The booking's own nights never block the change.">
                    <x-admin.input name="check_in" label="Check-in" :value="$booking->check_in?->toDateString()" data-datepicker span="md:col-span-2" />
                    <x-admin.input name="check_out" label="Check-out" :value="$booking->check_out?->toDateString()" data-datepicker span="md:col-span-2" />
                    <div class="md:col-span-6">
                        <p class="form-label">Rooms and guests</p>
                        <div class="space-y-2" data-rows="rooms"></div>
                        <button type="button" class="btn-ghost mt-2" data-add-row="rooms">Add room</button>
                    </div>
                </x-admin.form-section>
            @else
                <x-admin.form-section title="Date and travellers" description="The price is recalculated for the new date, party, units and add-ons (nationality group, season and partner commission of the booking). Extra travellers are added as placeholders you can rename on the booking.">
                    <x-admin.input name="travel_date" label="Travel date" :value="$booking->start_date?->toDateString()" data-datepicker span="md:col-span-2" />
                    <x-admin.input name="adults" label="Adults" type="number" min="1" :value="$booking->adults" span="md:col-span-1" />
                    <x-admin.input name="child_ages" label="Children ages" :value="$childAges" placeholder="e.g. 4, 9" hint="Comma separated, empty for none." span="md:col-span-2" />
                    @if ($product && ($product['min_persons'] || $product['max_persons']))
                        <p class="form-hint md:col-span-6">Persons per booking: {{ $product['min_persons'] ?? 1 }}{{ $product['max_persons'] ? '–'.$product['max_persons'] : '+' }}.</p>
                    @endif
                    @if ($product && $product['per_unit'] && $product['unit_options'] !== [])
                        <div class="md:col-span-6" data-units>
                            <p class="form-label">Units</p>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2" data-unit-list>
                                @foreach ($product['unit_options'] as $unit)
                                    <label class="flex items-center justify-between gap-3 rounded-control border border-ink-100 px-3 py-2 text-sm">
                                        <span>{{ $unit['name'] }} <span class="text-ink-500">({{ $unit['seats'] }} seats)</span></span>
                                        <input type="number" min="0" value="{{ $unitQuantities[$unit['id']] ?? 0 }}" class="form-control w-20" data-unit="{{ $unit['id'] }}" aria-label="{{ $unit['name'] }} quantity">
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($product && $product['addons'] !== [])
                        <div class="md:col-span-6" data-addons>
                            <p class="form-label">Add-ons</p>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2" data-addon-list>
                                @foreach ($product['addons'] as $addon)
                                    <div class="flex items-center justify-between gap-3 rounded-control border border-ink-100 px-3 py-2 text-sm">
                                        <label class="flex items-center gap-2">
                                            <input type="checkbox" data-addon="{{ $addon['id'] }}" @checked($addon['required'] || $addonQuantities->has($addon['id'])) @disabled($addon['required'])>
                                            <span>{{ $addon['name'] }}@if ($addon['required']) <span class="text-ink-500">(required)</span>@endif</span>
                                        </label>
                                        @if ($addon['pricing_basis'] === \App\Enums\AddonPricingBasis::PerUnit->value && ! $addon['required'])
                                            <input type="number" min="1" max="{{ $addon['max_quantity'] }}" value="{{ $addonQuantities[$addon['id']] ?? '' }}" placeholder="auto" class="form-control w-20" data-addon-quantity="{{ $addon['id'] }}" aria-label="{{ $addon['name'] }} quantity">
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($roomOptions !== [])
                        <div class="md:col-span-6" data-room-options>
                            <p class="form-label">Optional rooms</p>
                            <p class="form-hint mb-2">When rooms are used, every traveller must be in a room (per package stage): update them when the party changes.</p>
                            <div class="space-y-2" data-rows="room_options"></div>
                            <button type="button" class="btn-ghost mt-2" data-add-row="room_options">Add room</button>
                        </div>
                    @endif
                </x-admin.form-section>
            @endif
            <x-admin.form-section title="Reason">
                <x-admin.input name="note" label="Note for the booking history" span="md:col-span-6" />
            </x-admin.form-section>
        </div>

        <aside class="space-y-4">
            <x-admin.card title="Changes" class="xl:sticky xl:top-24">
                <div data-result aria-live="polite"><p class="text-sm text-ink-500">Preview the changes to see the new price before saving.</p></div>
                <div class="mt-4 flex flex-wrap gap-3">
                    <button type="button" class="btn-primary" data-action="quote">Preview changes</button>
                    <button type="button" class="btn-cta" data-action="submit" disabled>Save changes</button>
                    <a href="{{ route('admin.bookings.show', $booking) }}" class="btn-ghost">Cancel</a>
                </div>
            </x-admin.card>
        </aside>

        @include('admin.bookings.partials.editor-templates')
    </form>
</x-admin.layout>
