@php($prefix = "itinerary[{$dayIndex}][items][{$itemIndex}]")
@php($titleError = $errors->first("itinerary.{$dayIndex}.items.{$itemIndex}.title.en"))
<div data-repeater-row class="grid grid-cols-2 items-start gap-2 rounded-control border border-ink-100 bg-white p-3 md:grid-cols-[20px_90px_90px_minmax(0,1fr)_minmax(0,1fr)_minmax(0,0.8fr)_36px]">
    <button type="button" data-drag-handle class="hidden cursor-grab pt-3 text-ink-300 hover:text-ink-700 md:block" aria-label="Drag to reorder item"><x-admin.icon name="grip" size="16" /></button>
    <input type="time" name="{{ $prefix }}[time_from]" value="{{ isset($item['time_from']) ? substr($item['time_from'], 0, 5) : '' }}" aria-label="From time" class="form-control">
    <input type="time" name="{{ $prefix }}[time_to]" value="{{ isset($item['time_to']) ? substr($item['time_to'], 0, 5) : '' }}" aria-label="To time" class="form-control">
    <div class="col-span-2 md:col-span-1">
        <input type="text" name="{{ $prefix }}[title][en]" value="{{ $item['title']['en'] ?? '' }}" placeholder="Title (EN)" aria-label="Item title (English)" @class(['form-control', 'is-invalid' => $titleError])>
        @if ($titleError)<p class="form-error">{{ $titleError }}</p>@endif
    </div>
    <input type="text" name="{{ $prefix }}[title][it]" value="{{ $item['title']['it'] ?? '' }}" placeholder="Titolo (IT)" aria-label="Item title (Italiano)" class="form-control col-span-2 md:col-span-1">
    <input type="text" name="{{ $prefix }}[place]" value="{{ $item['place'] ?? '' }}" placeholder="Place" aria-label="Place" class="form-control">
    <button type="button" data-repeater-remove class="rounded-control p-2 text-ink-500 hover:bg-error/10 hover:text-error" aria-label="Remove item"><x-admin.icon name="trash" size="16" /></button>
    <textarea name="{{ $prefix }}[description][en]" rows="1" placeholder="Description (EN, optional)" aria-label="Item description (English)" class="form-control col-span-2 md:col-span-3 md:col-start-4">{{ $item['description']['en'] ?? '' }}</textarea>
</div>
