@php
    $isNew = ! $selected?->exists;
    $selectedCodes = old('countries', $isNew ? [] : $countries->where('nationality_group_id', $selected->id)->pluck('code')->all());
    $groupNames = $groups->mapWithKeys(fn ($group) => [$group->id => (string) $group->name]);
    $countryOptions = $countries->mapWithKeys(fn ($country) => [
        $country->code => $country->name.' ('.$country->code.')'.($country->nationality_group_id && $country->nationality_group_id !== $selected?->id ? ' · '.$groupNames[$country->nationality_group_id] : ''),
    ])->all();
    $unassignedCount = $unassigned->count();
@endphp
<x-admin.layout title="Nationality groups">
    <x-admin.page-header title="Nationality groups" :breadcrumbs="['Catalogue' => null, 'Nationality groups' => null]"
                         subtitle="Prices are set per group. Every country belongs to at most one group; the default group covers the rest.">
        <x-slot:actions><a href="{{ route('admin.nationality-groups.index', ['new' => 1]) }}" class="btn-cta">Add group</a></x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.7fr)]">
        <div class="space-y-4">
            <x-admin.card :padding="false">
                <table class="admin-table">
                    <thead><tr><th scope="col">Group</th><th scope="col" class="text-right">Countries</th></tr></thead>
                    <tbody>
                        @foreach ($groups as $group)
                            @php($count = $group->countries_count + ($group->is_default ? $unassignedCount : 0))
                            <tr @class(['bg-primary-100/50' => $selected?->id === $group->id])>
                                <td>
                                    <a href="{{ route('admin.nationality-groups.index', ['group' => $group->id]) }}" class="font-medium hover:underline" @if ($selected?->id === $group->id) aria-current="true" @endif>{{ $group->name }}</a>
                                    @if ($group->is_default)<span class="chip ml-1 bg-primary-100 text-primary-700">Default</span>@endif
                                    @unless ($group->is_active)<span class="chip ml-1 bg-ink-100 text-ink-700">Inactive</span>@endunless
                                </td>
                                <td class="text-right tabular-nums">{{ $count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-admin.card>

            <section class="card border border-warning/60 p-5" aria-labelledby="unassigned-title">
                <h2 id="unassigned-title" class="flex items-center gap-2 font-semibold text-ink-900">
                    <x-admin.icon name="alert" size="18" class="text-warning" /> {{ $unassignedCount }} {{ str('country')->plural($unassignedCount) }} without a group
                </h2>
                <p class="mt-1 text-sm text-ink-700">They use the default group “{{ $defaultGroup?->name }}”. Assign them if they need different prices.</p>
                <div class="mt-3 flex max-h-48 flex-wrap gap-1.5 overflow-y-auto">
                    @foreach ($unassigned as $country)
                        <span class="chip bg-ink-100 text-ink-700">{{ $country->name }}</span>
                    @endforeach
                </div>
            </section>
        </div>

        <x-admin.card :title="$isNew ? 'New group' : 'Edit group: '.$selected->name"
                      :subtitle="$isNew ? 'Name the group and choose its countries.' : count($selectedCodes).' '.str('country')->plural(count($selectedCodes)).' assigned'">
            <form id="group-form" method="POST" action="{{ $isNew ? route('admin.nationality-groups.store') : route('admin.nationality-groups.update', $selected) }}" class="space-y-5" novalidate>
                @csrf
                @unless ($isNew) @method('PUT') @endunless
                <div class="grid grid-cols-1 gap-5 md:grid-cols-6">
                    <x-admin.input name="name[en]" label="Name" :value="$selected?->getTranslations('name')['en'] ?? ''" required span="md:col-span-3" />
                    <x-admin.input name="name[it]" label="Name (Italiano)" :value="$selected?->getTranslations('name')['it'] ?? ''" span="md:col-span-2" />
                    <x-admin.input name="sort" label="Sort order" type="number" min="0" :value="$selected?->sort ?? 0" span="md:col-span-1" />
                    <x-admin.select name="countries[]" label="Countries" :options="$countryOptions" :selected="$selectedCodes" multiple placeholder="Type to search countries"
                                    hint="A country can belong to one group only; picking one from another group moves it here." span="md:col-span-6" />
                    <x-admin.switch name="is_default" label="Default group" :checked="$selected?->is_default ?? false"
                                    :description="'Used for every country without a group. Exactly one group is the default (currently “'.($defaultGroup?->name ?? '—').'”).'" span="md:col-span-3" />
                    <x-admin.switch name="is_active" label="Active" :checked="$selected?->is_active ?? true" description="Inactive groups price with the default group." span="md:col-span-3" />
                </div>
                @error('is_default')<p class="form-error">{{ $message }}</p>@enderror
                @error('group')<p class="form-error">{{ $message }}</p>@enderror
            </form>
            <div class="mt-6 flex flex-wrap justify-end gap-3 border-t border-ink-100 pt-5">
                @if (! $isNew && ! $selected->is_default)
                    <form method="POST" action="{{ route('admin.nationality-groups.destroy', $selected) }}" data-confirm="Delete “{{ $selected->name }}”? Its countries and group prices are removed; the countries will use the default group.">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-ghost text-error">Delete group</button>
                    </form>
                @endif
                <button type="submit" form="group-form" class="btn-cta">Save group</button>
            </div>
        </x-admin.card>
    </div>
</x-admin.layout>
