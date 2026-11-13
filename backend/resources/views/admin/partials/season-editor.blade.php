{{--
    SeasonPriceEditor (SRS 3.8) shared by trips and rooms.
    @param string $action      PUT endpoint
    @param \Illuminate\Support\Collection $seasons  SeasonPrice models
    @param list<array{label: string, key: string, partner?: bool}> $columns  price inputs per season (dot keys inside a row)
    @param string $productLabel e.g. "trip" or "room"
--}}
@php
    $rows = old('seasons') !== null
        ? array_values(old('seasons'))
        : $seasons->map->toEditorRow()->values()->all();
    $seasonErrors = collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, 'seasons'))->flatten()->unique();
    $fieldName = fn (string $index, string $key): string => "seasons[{$index}][".str_replace('.', '][', $key).']';
    $savedIds = $seasons->pluck('id')->all();
@endphp
<div data-season-editor>
    <x-admin.card title="Seasons" subtitle="The season containing the travel date (or each night) applies; outside every season the base price is used. Seasons cannot overlap.">
        <form method="POST" action="{{ $action }}" id="season-form" novalidate>
            @csrf
            @method('PUT')
            <div data-repeater data-placeholder="__SEASON__">
                <div data-repeater-list class="space-y-3">
                    @foreach ($rows as $index => $row)
                        @include('admin.partials.season-row', ['index' => (string) $index, 'row' => $row, 'saved' => in_array((int) ($row['id'] ?? 0), $savedIds, true)])
                    @endforeach
                </div>
                <p data-repeater-empty class="rounded-control border border-dashed border-ink-300 p-6 text-center text-sm text-ink-500" @if ($rows !== []) hidden @endif>
                    No seasons yet: the base price applies all year.
                </p>

                @if ($seasonErrors->isNotEmpty())
                    <div role="alert" class="mt-4 space-y-1 rounded-control bg-error/10 px-4 py-3 text-sm text-error">
                        @foreach ($seasonErrors as $message)
                            <p>{{ $message }} @if (str_contains($message, 'overlaps'))Change the dates so seasons do not overlap.@endif</p>
                        @endforeach
                    </div>
                @endif

                <template data-repeater-template>
                    @include('admin.partials.season-row', ['index' => '__SEASON__', 'row' => ['is_active' => true], 'saved' => false])
                </template>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <button type="button" class="btn-primary" data-repeater-add><x-admin.icon name="plus" size="18" /> Add season</button>
                    <button type="submit" class="btn-cta">Save price list</button>
                </div>
            </div>
        </form>
    </x-admin.card>

    @foreach ($seasons as $season)
        <form id="copy-season-{{ $season->id }}" method="POST" action="{{ route('admin.seasons.copy-next-year', $season) }}" class="hidden">@csrf</form>
    @endforeach

    <x-admin.card title="Timeline preview" subtitle="Next 12 months (active seasons)" class="mt-4">
        <div data-season-timeline></div>
    </x-admin.card>
</div>
