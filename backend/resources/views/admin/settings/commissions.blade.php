@php
    use App\Enums\CommissionCategory;
    $example = ['customer' => 115.00, 'percent' => (float) ($percents[CommissionCategory::SingleDayTrips->value] ?? 0)];
    $example['commission'] = round($example['customer'] * $example['percent'] / 100, 2);
@endphp
<x-admin.layout title="Commissions">
    <x-admin.page-header title="Settings" :breadcrumbs="['Settings' => null, 'Commissions' => null]" subtitle="Global configuration of the platform.">
        <x-slot:actions><button type="submit" form="commission-settings-form" class="btn-cta">Save changes</button></x-slot:actions>
    </x-admin.page-header>

    <nav aria-label="Settings sections" class="mb-5 flex overflow-x-auto border-b border-ink-300/60">
        @can('manage-settings')<a href="{{ route('admin.settings.edit') }}" class="tab-link">General</a>@endcan
        <a href="{{ route('admin.payment-methods.index') }}" class="tab-link">Payments</a>
        <a href="{{ route('admin.commissions.edit') }}" class="tab-link" aria-selected="true" aria-current="page">Commissions</a>
        <a href="{{ route('admin.nationality-groups.index') }}" class="tab-link">Nationality groups</a>
        @can('manage-settings')<a href="{{ route('admin.mcp.index') }}" class="tab-link">MCP access</a>@endcan
    </nav>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <form id="commission-settings-form" method="POST" action="{{ route('admin.commissions.update') }}" novalidate>
            @csrf @method('PUT')
            <x-admin.card title="Partner commissions" subtitle="Percentage discount on the customer price that partners get by default. A partner profile can override each type.">
                <ul class="divide-y divide-ink-100 border-t border-ink-100">
                    @foreach ($categories as $category)
                        @php($key = "commissions.{$category->value}")
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div>
                                <label for="commission-{{ $category->value }}" class="text-sm font-semibold text-ink-900">{{ $category->label() }}</label>
                                <p class="text-xs text-ink-500">{{ $category->description() }}</p>
                                @error($key)<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="relative w-32">
                                <input id="commission-{{ $category->value }}" type="number" step="0.01" min="0" max="100" required
                                       name="commissions[{{ $category->value }}]" value="{{ old($key, isset($percents[$category->value]) ? (float) $percents[$category->value] : 0) }}"
                                       @class(['form-control pr-8 font-semibold tabular-nums', 'is-invalid' => $errors->has($key)])>
                                <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-ink-500">%</span>
                            </div>
                        </li>
                    @endforeach
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3 text-ink-500">
                        <div>
                            <p class="text-sm font-semibold">Accommodation</p>
                            <p class="text-xs">Rooms and buildings</p>
                        </div>
                        <span class="chip bg-ink-100 text-ink-700">No commission</span>
                    </li>
                </ul>
                <p class="mt-3 flex items-start gap-2 rounded-control bg-primary-100/60 px-4 py-3 text-sm text-ink-700">
                    <x-admin.icon name="alert" size="18" class="mt-0.5 shrink-0" />
                    Accommodation has no partner commission: partners pay the customer price (nationality + season).
                </p>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-ink-500">Values 0–100. Changes apply to new bookings only; existing bookings keep their stored commission.</p>
                    <button type="submit" class="btn-cta">Save</button>
                </div>
            </x-admin.card>
        </form>

        <div class="space-y-4">
            <x-admin.card title="How the partner price is calculated">
                <ol class="space-y-2 text-sm text-ink-700">
                    <li class="flex gap-2"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700">1</span>Partner override for the type (if set)</li>
                    <li class="flex gap-2"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700">2</span>Global setting on this page</li>
                    <li class="flex gap-2"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700">3</span>Otherwise 0 %</li>
                </ol>
                <dl class="mt-4 space-y-1 border-t border-ink-100 pt-3 text-sm">
                    <p class="text-xs text-ink-500">Example · single-day trip</p>
                    <div class="flex justify-between"><dt>Customer price</dt><dd class="font-semibold tabular-nums">€{{ number_format($example['customer'], 2) }}</dd></div>
                    <div class="flex justify-between"><dt>Commission {{ rtrim(rtrim(number_format($example['percent'], 2), '0'), '.') }}% (Single-day trips)</dt><dd class="tabular-nums text-success">− €{{ number_format($example['commission'], 2) }}</dd></div>
                    <div class="flex justify-between"><dt>Partner price</dt><dd class="font-semibold tabular-nums text-partner-600">€{{ number_format($example['customer'] - $example['commission'], 2) }}</dd></div>
                </dl>
            </x-admin.card>

            <x-admin.card title="Partners with overrides" :subtitle="$overrides->count().' '.str('partner')->plural($overrides->count()).' use their own rate for at least one type.'">
                <ul class="space-y-2 text-sm">
                    @forelse ($overrides as $partner)
                        <li class="flex flex-wrap items-center justify-between gap-2">
                            <a href="{{ route('admin.partners.show', $partner) }}#tab-commissions" class="font-medium hover:underline">{{ $partner->company }}</a>
                            <span class="chip bg-partner-100 text-partner-600">{{ $partner->categoryCommissions->map(fn ($row) => $row->category->label().' '.(float) $row->commission_percent.'%')->implode(' · ') }}</span>
                        </li>
                    @empty
                        <li class="text-ink-500">No partner overrides: every partner uses the global rates.</li>
                    @endforelse
                </ul>
            </x-admin.card>
        </div>
    </div>
</x-admin.layout>
