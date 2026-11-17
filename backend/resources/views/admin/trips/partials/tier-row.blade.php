@php($err = fn (string $key) => $errors->first("private_tiers.{$index}.{$key}"))
<div data-repeater-row class="grid grid-cols-2 items-end gap-3 border-b border-ink-100 py-3 sm:grid-cols-[1fr_1fr_1.2fr_40px]">
    @if (! empty($row['id']))<input type="hidden" name="private_tiers[{{ $index }}][id]" value="{{ $row['id'] }}">@endif
    <div>
        <label class="form-label text-xs">From people</label>
        <input type="number" min="1" name="private_tiers[{{ $index }}][min_people]" value="{{ $row['min_people'] ?? '' }}" @class(['form-control', 'is-invalid' => $err('min_people')])>
    </div>
    <div>
        <label class="form-label text-xs">To people</label>
        <input type="number" min="1" name="private_tiers[{{ $index }}][max_people]" value="{{ $row['max_people'] ?? '' }}" placeholder="No limit" @class(['form-control', 'is-invalid' => $err('max_people')])>
    </div>
    <div>
        <label class="form-label text-xs">Price per person</label>
        <input type="number" step="0.01" min="0" name="private_tiers[{{ $index }}][price_per_person]" value="{{ $row['price_per_person'] ?? '' }}" placeholder="€" @class(['form-control', 'is-invalid' => $err('price_per_person')])>
    </div>
    <button type="button" data-repeater-remove class="rounded-control p-2 text-ink-500 hover:bg-error/10 hover:text-error" aria-label="Remove tier"><x-admin.icon name="trash" size="18" /></button>
    @foreach (['min_people', 'max_people', 'price_per_person'] as $field)
        @if ($err($field))<p class="form-error col-span-full">{{ $err($field) }}</p>@endif
    @endforeach
</div>
