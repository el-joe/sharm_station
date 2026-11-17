@php($err = fn (string $key) => $errors->first("unit_options.{$index}.{$key}"))
<div data-repeater-row class="grid grid-cols-2 items-start gap-2 border-b border-ink-100 py-3 md:grid-cols-[24px_minmax(0,2.2fr)_70px_minmax(0,1fr)_80px_64px_40px] md:items-center">
    <button type="button" data-drag-handle class="hidden cursor-grab text-ink-300 hover:text-ink-700 md:block" aria-label="Drag to reorder"><x-admin.icon name="grip" size="18" /></button>
    @if (! empty($row['id']))<input type="hidden" name="unit_options[{{ $index }}][id]" value="{{ $row['id'] }}">@endif
    <input type="hidden" name="unit_options[{{ $index }}][sort]" value="{{ $row['sort'] ?? 0 }}" data-sort-input>
    <div class="col-span-2 md:col-span-1">
        <label class="form-label text-xs md:sr-only">Option name (EN / IT)</label>
        <div class="grid grid-cols-2 gap-2">
            <input type="text" name="unit_options[{{ $index }}][name][en]" value="{{ $row['name']['en'] ?? '' }}" placeholder="Single buggy" aria-label="Option name (English)" @class(['form-control', 'is-invalid' => $err('name.en')])>
            <input type="text" name="unit_options[{{ $index }}][name][it]" value="{{ $row['name']['it'] ?? '' }}" placeholder="Buggy singolo" aria-label="Option name (Italiano)" class="form-control">
        </div>
    </div>
    <div>
        <label class="form-label text-xs md:sr-only">Seats</label>
        <input type="number" min="1" name="unit_options[{{ $index }}][seats]" value="{{ $row['seats'] ?? 1 }}" aria-label="Seats" @class(['form-control', 'is-invalid' => $err('seats')])>
    </div>
    <div>
        <label class="form-label text-xs md:sr-only">Base price (EUR)</label>
        <input type="number" step="0.01" min="0" name="unit_options[{{ $index }}][price]" value="{{ $row['price'] ?? '' }}" placeholder="€" aria-label="Base price (EUR)" @class(['form-control', 'is-invalid' => $err('price')])>
    </div>
    <div>
        <label class="form-label text-xs md:sr-only">Max qty</label>
        <input type="number" min="1" name="unit_options[{{ $index }}][max_quantity]" value="{{ $row['max_quantity'] ?? 10 }}" aria-label="Maximum quantity" @class(['form-control', 'is-invalid' => $err('max_quantity')])>
    </div>
    <label class="flex items-center gap-2 text-xs md:justify-center" title="Children allowed">
        <input type="hidden" name="unit_options[{{ $index }}][children_allowed]" value="0">
        <input type="checkbox" name="unit_options[{{ $index }}][children_allowed]" value="1" class="size-4 accent-primary-700" @checked($row['children_allowed'] ?? true)>
        <span class="md:sr-only">Children allowed</span>
    </label>
    <button type="button" data-repeater-remove class="justify-self-end rounded-control p-2 text-ink-500 hover:bg-error/10 hover:text-error" aria-label="Remove option"><x-admin.icon name="trash" size="18" /></button>
    @foreach (['name.en', 'seats', 'price', 'max_quantity'] as $field)
        @if ($err($field))<p class="form-error col-span-full">{{ $err($field) }}</p>@endif
    @endforeach
</div>
