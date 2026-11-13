@php($err = fn (string $key) => $errors->first("itinerary.{$dayIndex}.{$key}"))
<div data-repeater-row data-day-row class="rounded-card border border-ink-100 bg-sand-50/60 p-4">
    <div class="flex items-center gap-3">
        <button type="button" data-drag-handle class="cursor-grab text-ink-300 hover:text-ink-700" aria-label="Drag to reorder day"><x-admin.icon name="grip" size="18" /></button>
        <span class="chip border-primary-100 bg-primary-100 text-primary-700" data-day-label>Day</span>
        <button type="button" data-repeater-remove class="ml-auto rounded-control p-2 text-ink-500 hover:bg-error/10 hover:text-error" aria-label="Remove day"><x-admin.icon name="trash" size="18" /></button>
    </div>
    <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
        <div>
            <label class="form-label text-xs">Day title (EN)</label>
            <input type="text" name="itinerary[{{ $dayIndex }}][title][en]" value="{{ $day['title']['en'] ?? '' }}" @class(['form-control', 'is-invalid' => $err('title.en')])>
            @if ($err('title.en'))<p class="form-error">{{ $err('title.en') }}</p>@endif
        </div>
        <div>
            <label class="form-label text-xs">Day title (IT)</label>
            <input type="text" name="itinerary[{{ $dayIndex }}][title][it]" value="{{ $day['title']['it'] ?? '' }}" class="form-control">
        </div>
        <div>
            <label class="form-label text-xs">Description (EN)</label>
            <textarea name="itinerary[{{ $dayIndex }}][description][en]" rows="2" class="form-control">{{ $day['description']['en'] ?? '' }}</textarea>
        </div>
        <div>
            <label class="form-label text-xs">Description (IT)</label>
            <textarea name="itinerary[{{ $dayIndex }}][description][it]" rows="2" class="form-control">{{ $day['description']['it'] ?? '' }}</textarea>
        </div>
        <div>
            <label class="form-label text-xs">Overnight</label>
            <input type="text" name="itinerary[{{ $dayIndex }}][overnight]" value="{{ $day['overnight'] ?? '' }}" placeholder="e.g. Luxor, on board" class="form-control">
        </div>
    </div>

    <div class="mt-4" data-repeater data-placeholder="__ITEM__" data-sortable>
        <p class="text-xs font-semibold tracking-wide text-ink-500 uppercase">Timeline</p>
        <div data-repeater-list class="mt-2 space-y-2">
            @foreach (array_values($day['items'] ?? []) as $itemIndex => $item)
                @include('admin.trips.partials.item-row', ['dayIndex' => $dayIndex, 'itemIndex' => (string) $itemIndex, 'item' => $item])
            @endforeach
        </div>
        <template data-repeater-template>
            @include('admin.trips.partials.item-row', ['dayIndex' => $dayIndex, 'itemIndex' => '__ITEM__', 'item' => []])
        </template>
        <button type="button" data-repeater-add class="btn-link mt-2"><x-admin.icon name="plus" size="16" /> Add timeline item</button>
    </div>
</div>
