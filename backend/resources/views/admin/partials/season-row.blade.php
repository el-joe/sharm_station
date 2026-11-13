@php
    $err = fn (string $key) => $errors->first("seasons.{$index}.{$key}");
    $name = fn (string $key): string => "seasons[{$index}][".str_replace('.', '][', $key).']';
@endphp
<div data-repeater-row class="rounded-card border border-ink-100 bg-white p-4">
    @if (! empty($row['id']))<input type="hidden" name="{{ $name('id') }}" value="{{ $row['id'] }}">@endif
    <div class="grid grid-cols-2 gap-3 md:grid-cols-12">
        <div class="col-span-2 md:col-span-4">
            <label class="form-label text-xs" for="s-{{ $index }}-name">Season name</label>
            <input id="s-{{ $index }}-name" type="text" name="{{ $name('name.en') }}" value="{{ $row['name']['en'] ?? '' }}" data-season-name placeholder="High season" @class(['form-control', 'is-invalid' => $err('name.en')])>
            <input type="text" name="{{ $name('name.it') }}" value="{{ $row['name']['it'] ?? '' }}" placeholder="Italiano (optional)" aria-label="Season name (Italiano)" class="form-control mt-2 min-h-9 text-xs">
            @if ($err('name.en'))<p class="form-error">{{ $err('name.en') }}</p>@endif
        </div>
        <div class="md:col-span-2">
            <label class="form-label text-xs" for="s-{{ $index }}-from">From</label>
            <input id="s-{{ $index }}-from" type="text" name="{{ $name('starts_on') }}" value="{{ $row['starts_on'] ?? '' }}" data-datepicker data-season-from placeholder="dd/mm/yyyy" @class(['form-control', 'is-invalid' => $err('starts_on')])>
        </div>
        <div class="md:col-span-2">
            <label class="form-label text-xs" for="s-{{ $index }}-to">To</label>
            <input id="s-{{ $index }}-to" type="text" name="{{ $name('ends_on') }}" value="{{ $row['ends_on'] ?? '' }}" data-datepicker data-season-to placeholder="dd/mm/yyyy" @class(['form-control', 'is-invalid' => $err('ends_on')])>
        </div>
        <div class="col-span-2 flex flex-wrap items-end gap-x-5 gap-y-2 md:col-span-4">
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="{{ $name('is_active') }}" value="0">
                <input type="checkbox" name="{{ $name('is_active') }}" value="1" data-season-active class="size-4 accent-primary-700" @checked($row['is_active'] ?? true)> Active
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="{{ $name('recurring_yearly') }}" value="0">
                <input type="checkbox" name="{{ $name('recurring_yearly') }}" value="1" data-season-yearly class="size-4 accent-primary-700" @checked($row['recurring_yearly'] ?? false)> Yearly
            </label>
            <div class="ml-auto flex items-center gap-3">
                @if ($saved)
                    <button type="submit" form="copy-season-{{ $row['id'] }}" class="btn-link text-xs" title="Duplicate this season one year later">Copy to next year</button>
                @endif
                <button type="button" data-repeater-remove class="rounded-control p-2 text-ink-500 hover:bg-error/10 hover:text-error" aria-label="Remove season"><x-admin.icon name="trash" size="18" /></button>
            </div>
        </div>
    </div>
    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ($columns as $column)
            @php($value = data_get($row, $column['key']))
            <div>
                <label class="form-label text-xs" for="s-{{ $index }}-{{ $loop->index }}">{{ $column['label'] }}</label>
                <div class="relative">
                    <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-ink-500">€</span>
                    <input id="s-{{ $index }}-{{ $loop->index }}" type="number" step="0.01" min="0" name="{{ $name($column['key']) }}"
                           value="{{ $value !== null && $value !== '' ? number_format((float) $value, 2, '.', '') : '' }}"
                           placeholder="{{ ($column['partner'] ?? false) ? 'Rule' : 'Base' }}"
                           @class(['form-control pl-7', 'border-partner-600/40 bg-partner-100/40' => $column['partner'] ?? false, 'is-invalid' => $err($column['key'])])>
                </div>
            </div>
        @endforeach
    </div>
</div>
