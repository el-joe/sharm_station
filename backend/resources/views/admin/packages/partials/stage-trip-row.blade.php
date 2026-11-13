@php($err = fn (string $key) => $errors->first("stages.{$stageIndex}.trips.{$tripIndex}.{$key}"))
<div data-repeater-row class="grid grid-cols-[90px_minmax(0,1fr)_40px] items-end gap-2">
    <div>
        <label class="form-label text-xs">Stage day</label>
        <input type="number" min="1" name="stages[{{ $stageIndex }}][trips][{{ $tripIndex }}][day_number]" value="{{ $trip['day_number'] ?? 1 }}" @class(['form-control', 'is-invalid' => $err('day_number')])>
    </div>
    <div>
        <label class="form-label text-xs" for="stage-{{ $stageIndex }}-trip-{{ $tripIndex }}">Trip</label>
        <select id="stage-{{ $stageIndex }}-trip-{{ $tripIndex }}" name="stages[{{ $stageIndex }}][trips][{{ $tripIndex }}][trip_id]" data-s2 data-placeholder="Search trips" @class(['form-control', 'is-invalid' => $err('trip_id')])>
            <option value=""></option>
            @foreach ($trips as $id => $title)
                <option value="{{ $id }}" @selected((string) ($trip['trip_id'] ?? '') === (string) $id)>{{ $title }}</option>
            @endforeach
        </select>
    </div>
    <button type="button" data-repeater-remove class="rounded-control p-2 text-ink-500 hover:bg-error/10 hover:text-error" aria-label="Remove trip"><x-admin.icon name="trash" size="18" /></button>
    @foreach (['day_number', 'trip_id'] as $field)
        @if ($err($field))<p class="form-error col-span-full">{{ $err($field) }}</p>@endif
    @endforeach
</div>
