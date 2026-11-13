@php($err = fn (string $key) => $errors->first("stages.{$stageIndex}.{$key}"))
<div data-repeater-row class="rounded-card border border-ink-100 bg-sand-50/60 p-4">
    <div class="flex items-center gap-3">
        <button type="button" data-drag-handle class="cursor-grab text-ink-300 hover:text-ink-700" aria-label="Drag to reorder stage"><x-admin.icon name="grip" size="18" /></button>
        <span class="chip border-primary-100 bg-primary-100 text-primary-700">Stage <span data-row-number>{{ is_numeric($stageIndex) ? $stageIndex + 1 : '' }}</span></span>
        <button type="button" data-repeater-remove class="ml-auto rounded-control p-2 text-ink-500 hover:bg-error/10 hover:text-error" aria-label="Remove stage"><x-admin.icon name="trash" size="18" /></button>
    </div>
    <div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-12">
        <div class="col-span-2 md:col-span-4">
            <label class="form-label text-xs" for="stage-{{ $stageIndex }}-destination">Destination</label>
            <select id="stage-{{ $stageIndex }}-destination" name="stages[{{ $stageIndex }}][destination_id]" data-s2 data-placeholder="Choose" @class(['form-control', 'is-invalid' => $err('destination_id')])>
                <option value=""></option>
                @foreach ($destinations as $id => $name)
                    <option value="{{ $id }}" @selected((string) ($stage['destination_id'] ?? '') === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
            @if ($err('destination_id'))<p class="form-error">{{ $err('destination_id') }}</p>@endif
        </div>
        <div class="md:col-span-2">
            <label class="form-label text-xs">Days</label>
            <input type="number" min="1" name="stages[{{ $stageIndex }}][days]" value="{{ $stage['days'] ?? 1 }}" @class(['form-control', 'is-invalid' => $err('days')])>
        </div>
        <div class="md:col-span-2">
            <label class="form-label text-xs">Nights</label>
            <input type="number" min="0" name="stages[{{ $stageIndex }}][nights]" value="{{ $stage['nights'] ?? 0 }}" class="form-control">
        </div>
        <div class="col-span-2 md:col-span-2">
            <label class="form-label text-xs">Accommodation (EN)</label>
            <input type="text" name="stages[{{ $stageIndex }}][accommodation][en]" value="{{ $stage['accommodation']['en'] ?? '' }}" placeholder="Hotel 4★" class="form-control">
        </div>
        <div class="col-span-2 md:col-span-2">
            <label class="form-label text-xs">Accommodation (IT)</label>
            <input type="text" name="stages[{{ $stageIndex }}][accommodation][it]" value="{{ $stage['accommodation']['it'] ?? '' }}" class="form-control">
        </div>
    </div>

    <div class="mt-4" data-repeater data-placeholder="__TRIP__">
        <p class="text-xs font-semibold tracking-wide text-ink-500 uppercase">Trips on the days of this stage</p>
        <div data-repeater-list class="mt-2 space-y-2">
            @foreach (array_values($stage['trips'] ?? []) as $tripIndex => $trip)
                @include('admin.packages.partials.stage-trip-row', ['stageIndex' => $stageIndex, 'tripIndex' => (string) $tripIndex, 'trip' => $trip])
            @endforeach
        </div>
        <p data-repeater-empty class="py-2 text-sm text-ink-500" @if (! empty($stage['trips'])) hidden @endif>No trips on this stage (free days).</p>
        <template data-repeater-template>
            @include('admin.packages.partials.stage-trip-row', ['stageIndex' => $stageIndex, 'tripIndex' => '__TRIP__', 'trip' => []])
        </template>
        <button type="button" data-repeater-add class="btn-link mt-2"><x-admin.icon name="plus" size="16" /> Add trip to a day</button>
    </div>
</div>
