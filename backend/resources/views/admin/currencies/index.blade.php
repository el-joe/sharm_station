<x-admin.layout title="Currencies">
    <x-admin.page-header title="Currencies" :breadcrumbs="['Settings' => null, 'Currencies' => null]" subtitle="EUR is the base currency. Customer prices = EUR × rate × (1 + margin).">
        <x-slot:actions><button type="submit" form="currency-form" class="btn-cta">Save currencies</button></x-slot:actions>
    </x-admin.page-header>
    <form id="currency-form" method="POST" action="{{ route('admin.currencies.update') }}" novalidate>
        @csrf @method('PUT')
        <x-admin.card :padding="false">
            <div class="overflow-x-auto">
                <table class="admin-table min-w-[720px]">
                    <thead><tr><th scope="col">Currency</th><th scope="col">Rate to EUR</th><th scope="col">Margin %</th><th scope="col">Effective rate</th><th scope="col">Manual rate</th><th scope="col">Enabled</th></tr></thead>
                    <tbody>
                        @foreach ($currencies as $currency)
                            @php($base = $currency->code === \App\Services\CurrencyService::BASE)
                            @php($prefix = "currencies.{$currency->id}")
                            <tr>
                                <th scope="row" class="border-t border-ink-100 px-4 py-3 text-left font-medium">{{ $currency->symbol }} {{ $currency->code }} <span class="block text-xs font-normal text-ink-500">{{ $currency->name }}</span></th>
                                <td><input type="number" step="0.000001" min="0" name="currencies[{{ $currency->id }}][rate_to_eur]" value="{{ old($prefix.'.rate_to_eur', (float) $currency->rate_to_eur) }}" aria-label="{{ $currency->code }} rate" @readonly($base) @class(['form-control w-32', 'is-invalid' => $errors->has($prefix.'.rate_to_eur')])></td>
                                <td><input type="number" step="0.01" min="0" name="currencies[{{ $currency->id }}][margin_percent]" value="{{ old($prefix.'.margin_percent', (float) $currency->margin_percent) }}" aria-label="{{ $currency->code }} margin" @readonly($base) @class(['form-control w-24', 'is-invalid' => $errors->has($prefix.'.margin_percent')])></td>
                                <td class="tabular-nums">{{ number_format($currency->effectiveRate(), 4) }}</td>
                                <td><input type="hidden" name="currencies[{{ $currency->id }}][is_manual_rate]" value="0"><input type="checkbox" name="currencies[{{ $currency->id }}][is_manual_rate]" value="1" aria-label="{{ $currency->code }} manual rate" class="size-4 accent-primary-700" @checked($currency->is_manual_rate) @disabled($base)></td>
                                <td><input type="hidden" name="currencies[{{ $currency->id }}][is_enabled]" value="{{ $base ? 1 : 0 }}"><input type="checkbox" name="currencies[{{ $currency->id }}][is_enabled]" value="1" aria-label="{{ $currency->code }} enabled" class="size-4 accent-primary-700" @checked($currency->is_enabled) @disabled($base)></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="border-t border-ink-100 px-5 py-3 text-xs text-ink-500">Automatic rate updates are not connected yet; rates entered here are used as they are.</p>
        </x-admin.card>
    </form>
</x-admin.layout>
