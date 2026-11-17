{{--
    Price matrix (SRS 3.8, 3.9) shared by trips, packages, rooms and transfer fixed routes:
    base price per nationality group + seasons for one group or all groups. One form saves both.
    @param string $action  PUT endpoint
    @param array  $matrix  PriceMatrixService::editorData()
--}}
@php
    $fields = $matrix['fields'];
    $groups = $matrix['groups'];
    $seasons = $matrix['seasons'];
    $inputName = fn (string $prefix, string $field): string => $prefix.'['.str_replace('.', '][', $field).']';
    $rows = old('seasons') !== null
        ? array_values(old('seasons'))
        : $seasons->map->toEditorRow()->values()->all();
    $seasonErrors = collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, 'seasons'))->flatten()->unique();
    $baseErrors = collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, 'base'))->flatten()->unique();
    $savedIds = $seasons->pluck('id')->all();
    $groupOptions = collect($groups)->mapWithKeys(fn ($group) => [$group->id => (string) $group->name])->all();
    $columns = collect($fields)->map(fn ($label, $key) => ['label' => $label, 'key' => $key])->values()->all();
@endphp
<div data-season-editor>
    <form method="POST" action="{{ $action }}" id="season-form" novalidate class="space-y-4">
        @csrf
        @method('PUT')

        <x-admin.card title="Base price per nationality group" subtitle="Applies outside every season. Leave a cell empty to use the product base price (shown as placeholder).">
            <div class="overflow-x-auto">
                <table class="admin-table min-w-[640px]">
                    <thead>
                        <tr>
                            <th scope="col">Nationality group</th>
                            @foreach ($fields as $label)<th scope="col">{{ $label }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groups as $group)
                            <tr>
                                <th scope="row" class="px-4 py-3 text-left font-semibold whitespace-nowrap">
                                    {{ $group->name }}
                                    @if ($group->is_default)<span class="chip ml-1 bg-primary-100 text-primary-700">Default</span>@endif
                                </th>
                                @foreach ($fields as $field => $label)
                                    @php
                                        $key = "base.{$group->id}.{$field}";
                                        $value = old($key, $matrix['base'][$group->id][$field] ?? null);
                                        $fallback = $matrix['product_base'][$field] ?? null;
                                    @endphp
                                    <td>
                                        <div class="relative min-w-28">
                                            <input type="number" step="0.01" min="0" name="{{ $inputName("base[{$group->id}]", $field) }}"
                                                   aria-label="{{ $group->name }} {{ $label }} (EUR)"
                                                   value="{{ $value !== null && $value !== '' ? number_format((float) $value, 2, '.', '') : '' }}"
                                                   placeholder="{{ $fallback !== null ? number_format((float) $fallback, 2, '.', '') : '—' }}"
                                                   @class(['form-control pr-7', 'is-invalid' => $errors->has($key)])>
                                            <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-ink-500">€</span>
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($baseErrors->isNotEmpty())
                <div role="alert" class="mt-3 rounded-control bg-error/10 px-4 py-3 text-sm text-error">{{ $baseErrors->first() }}</div>
            @endif
            <p class="mt-4 flex items-start gap-2 rounded-control bg-primary-100/60 px-4 py-3 text-sm text-ink-700">
                <x-admin.icon name="alert" size="18" class="mt-0.5 shrink-0" />
                No partner-price fields: partner prices are always the customer price minus the partner commission (Settings → Commissions).
            </p>
        </x-admin.card>

        <x-admin.card title="Seasons" subtitle="A season can apply to all groups or to one group. Seasons of the same group cannot overlap; a group's own season wins over an all-groups season.">
            <div data-repeater data-placeholder="__SEASON__">
                <div data-repeater-list class="space-y-3">
                    @foreach ($rows as $index => $row)
                        @include('admin.partials.season-row', ['index' => (string) $index, 'row' => $row, 'saved' => in_array((int) ($row['id'] ?? 0), $savedIds, true), 'columns' => $columns, 'groupOptions' => $groupOptions])
                    @endforeach
                </div>
                <p data-repeater-empty class="rounded-control border border-dashed border-ink-300 p-6 text-center text-sm text-ink-500" @if ($rows !== []) hidden @endif>
                    No seasons yet: the base price of each group applies all year.
                </p>

                @if ($seasonErrors->isNotEmpty())
                    <div role="alert" class="mt-4 space-y-1 rounded-control border border-error/40 bg-error/10 px-4 py-3 text-sm text-error">
                        @foreach ($seasonErrors as $message)
                            <p>{{ $message }} @if (str_contains($message, 'overlaps'))Change the dates or apply one of them to a different group.@endif</p>
                        @endforeach
                    </div>
                @endif

                <template data-repeater-template>
                    @include('admin.partials.season-row', ['index' => '__SEASON__', 'row' => ['is_active' => true], 'saved' => false, 'columns' => $columns, 'groupOptions' => $groupOptions])
                </template>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <button type="button" class="btn-primary" data-repeater-add><x-admin.icon name="plus" size="18" /> Add season</button>
                    <button type="submit" class="btn-cta">Save price matrix</button>
                </div>
            </div>
        </x-admin.card>
    </form>

    @foreach ($seasons as $season)
        <form id="copy-season-{{ $season->id }}" method="POST" action="{{ route('admin.seasons.copy-next-year', $season) }}" class="hidden">@csrf</form>
    @endforeach

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <x-admin.card title="Timeline preview" subtitle="Next 12 months (active seasons)" class="lg:col-span-2">
            <div data-season-timeline></div>
        </x-admin.card>
        <x-admin.card title="Which price applies?">
            <ol class="list-decimal space-y-1 pl-5 text-sm text-ink-700">
                <li>Season for the customer's group on the travel date (each night for rooms)</li>
                <li>Season for all groups</li>
                <li>Base price of the group (table above)</li>
                <li>Product base price</li>
            </ol>
        </x-admin.card>
    </div>
</div>
