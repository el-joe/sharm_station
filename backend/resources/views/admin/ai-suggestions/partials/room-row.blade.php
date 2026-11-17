{{-- One editable room of an accommodation suggestion ($index may be the "__INDEX__" template placeholder). --}}
@php
    $err = fn (string $key) => $errors->first("rooms.{$index}.{$key}");
    $field = fn (string $key, mixed $default = null) => data_get($room, $key, $default);
    $base = "rooms[{$index}]";
@endphp
<div data-repeater-row class="rounded-card border border-ink-100 bg-sand-50/60 p-4">
    <div class="mb-3 flex items-center gap-2">
        <span class="text-sm font-semibold text-ink-900">Room <span data-row-number>{{ is_numeric($index) ? $index + 1 : '' }}</span></span>
        @if ($editable)
            <button type="button" data-repeater-remove class="ml-auto rounded-control p-2 text-ink-500 hover:bg-error/10 hover:text-error" aria-label="Remove room"><x-admin.icon name="x" size="18" /></button>
        @endif
    </div>
    <fieldset class="grid grid-cols-2 gap-3 md:grid-cols-12" @disabled(! $editable)>
        <div class="col-span-2 md:col-span-4">
            <label class="form-label text-xs" for="room-{{ $index }}-name-en">Room name (EN) <span class="text-error">*</span></label>
            <input id="room-{{ $index }}-name-en" type="text" name="{{ $base }}[name][en]" value="{{ $field('name.en') }}" maxlength="160" @class(['form-control', 'is-invalid' => $err('name.en')])>
        </div>
        <div class="col-span-2 md:col-span-3">
            <label class="form-label text-xs" for="room-{{ $index }}-name-it">Room name (IT)</label>
            <input id="room-{{ $index }}-name-it" type="text" name="{{ $base }}[name][it]" value="{{ $field('name.it') }}" maxlength="160" class="form-control">
        </div>
        <div class="md:col-span-2">
            <label class="form-label text-xs" for="room-{{ $index }}-type">Type</label>
            <select id="room-{{ $index }}-type" name="{{ $base }}[room_type]" @class(['form-control', 'is-invalid' => $err('room_type')])>
                @foreach ($roomTypes as $value => $label)
                    <option value="{{ $value }}" @selected($field('room_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-3">
            <label class="form-label text-xs" for="room-{{ $index }}-price">Base / night <span class="text-error">*</span></label>
            <div class="relative">
                <input id="room-{{ $index }}-price" type="number" step="0.01" min="0" name="{{ $base }}[base_price]" value="{{ $field('base_price') }}" @class(['form-control pr-8 tabular-nums', 'is-invalid' => $err('base_price')])>
                <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-ink-500">€</span>
            </div>
        </div>
        @foreach (['max_adults' => 'Max adults', 'max_children' => 'Max children', 'max_total' => 'Max total', 'size_m2' => 'Size m²', 'min_nights' => 'Min nights', 'max_nights' => 'Max nights'] as $key => $label)
            <div class="md:col-span-2">
                <label class="form-label text-xs" for="room-{{ $index }}-{{ $key }}">{{ $label }}</label>
                <input id="room-{{ $index }}-{{ $key }}" type="number" min="0" name="{{ $base }}[{{ $key }}]" value="{{ $field($key, $key === 'max_children' ? 0 : ($key === 'min_nights' ? 1 : null)) }}" @class(['form-control tabular-nums', 'is-invalid' => $err($key)])>
            </div>
        @endforeach
        <div class="col-span-2 md:col-span-6">
            <label class="form-label text-xs" for="room-{{ $index }}-beds">Beds (EN)</label>
            <input id="room-{{ $index }}-beds" type="text" name="{{ $base }}[beds][en]" value="{{ $field('beds.en') }}" maxlength="120" class="form-control">
        </div>
        <div class="col-span-2 md:col-span-6">
            <label class="form-label text-xs" for="room-{{ $index }}-amenities">Amenities</label>
            <select id="room-{{ $index }}-amenities" name="{{ $base }}[amenities][]" multiple data-s2 data-s2-tags data-placeholder="Add amenities" class="form-control">
                @foreach ((array) $field('amenities', []) as $amenity)
                    <option value="{{ $amenity }}" selected>{{ $amenity }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-span-2 md:col-span-12">
            <label class="form-label text-xs" for="room-{{ $index }}-description">Description (EN)</label>
            <textarea id="room-{{ $index }}-description" name="{{ $base }}[description][en]" rows="2" class="form-control">{{ $field('description.en') }}</textarea>
            <input type="hidden" name="{{ $base }}[description][it]" value="{{ $field('description.it') }}">
            <input type="hidden" name="{{ $base }}[beds][it]" value="{{ $field('beds.it') }}">
        </div>
    </fieldset>
    @php($messages = collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, "rooms.{$index}."))->flatten()->unique())
    @if ($messages->isNotEmpty())
        <ul class="mt-2 space-y-0.5">@foreach ($messages as $message)<li class="form-error">{{ $message }}</li>@endforeach</ul>
    @endif
</div>
