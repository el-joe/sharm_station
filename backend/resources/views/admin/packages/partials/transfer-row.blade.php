@php($err = fn (string $key) => $errors->first("transfers.{$index}.{$key}"))
<div data-repeater-row class="grid grid-cols-2 items-end gap-3 border-b border-ink-100 py-3 md:grid-cols-[80px_100px_minmax(0,1fr)_minmax(0,1fr)_140px_40px]">
    <div>
        <label class="form-label text-xs">Day</label>
        <input type="number" min="1" name="transfers[{{ $index }}][day_number]" value="{{ $row['day_number'] ?? 1 }}" @class(['form-control', 'is-invalid' => $err('day_number')])>
    </div>
    <div>
        <label class="form-label text-xs">Time</label>
        <input type="time" name="transfers[{{ $index }}][time]" value="{{ isset($row['time']) ? substr((string) $row['time'], 0, 5) : '' }}" @class(['form-control', 'is-invalid' => $err('time')])>
    </div>
    <div>
        <label class="form-label text-xs">From</label>
        <input type="text" name="transfers[{{ $index }}][from_place]" value="{{ $row['from_place'] ?? '' }}" placeholder="Cairo airport" @class(['form-control', 'is-invalid' => $err('from_place')])>
    </div>
    <div>
        <label class="form-label text-xs">To</label>
        <input type="text" name="transfers[{{ $index }}][to_place]" value="{{ $row['to_place'] ?? '' }}" placeholder="Hotel" @class(['form-control', 'is-invalid' => $err('to_place')])>
    </div>
    <div>
        <label class="form-label text-xs">Vehicle</label>
        <select name="transfers[{{ $index }}][vehicle_type]" class="form-control">
            <option value="">Any</option>
            @foreach (\App\Enums\VehicleType::options() as $value => $label)
                <option value="{{ $value }}" @selected(($row['vehicle_type'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <button type="button" data-repeater-remove class="rounded-control p-2 text-ink-500 hover:bg-error/10 hover:text-error" aria-label="Remove transfer"><x-admin.icon name="trash" size="18" /></button>
    @foreach (['day_number', 'time', 'from_place', 'to_place'] as $field)
        @if ($err($field))<p class="form-error col-span-full">{{ $err($field) }}</p>@endif
    @endforeach
</div>
