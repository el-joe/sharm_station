@php
    use Illuminate\Support\Number;
    $eur = fn ($amount) => Number::currency((float) $amount, 'EUR', 'en', 0);
    $eur2 = fn ($amount) => Number::currency((float) $amount, 'EUR', 'en');
    $query = $filters->toQuery();
@endphp
<x-admin.layout title="Reports">
    <x-admin.page-header title="Reports" :breadcrumbs="['Overview' => route('admin.dashboard'), 'Reports' => null]"
                         :subtitle="'Bookings created '.$filters->from->format('j M Y').' – '.$filters->to->format('j M Y').' · cancelled and refunded excluded · EUR'" />

    <form method="GET" action="{{ route('admin.reports.index') }}" class="card mb-4 p-4" aria-label="Filters">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-admin.input name="date_from" label="From" data-datepicker :value="$filters->from->toDateString()" span="" />
            <x-admin.input name="date_to" label="To" data-datepicker :value="$filters->to->toDateString()" span="" />
            <x-admin.select name="service_type" label="Service" :options="$services" :selected="$filters->serviceType" placeholder="All services" data-s2-clear span="" />
            <div class="flex items-end gap-2"><button type="submit" class="btn-primary w-full">Show report</button><a href="{{ route('admin.reports.index') }}" class="btn-ghost">Reset</a></div>
        </div>
    </form>

    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-3">
        <x-admin.kpi label="Bookings" :value="Number::format($kpis['bookings'])" icon="calendar" />
        <x-admin.kpi label="Revenue" :value="$eur($kpis['revenue'])" icon="euro" :hint="$kpis['discounts'] > 0 ? 'After '.$eur2($kpis['discounts']).' of coupon discounts' : null" />
        <x-admin.kpi label="Deposits collected" :value="$eur($kpis['deposits_collected'])" icon="wallet" hint="Deposit payments received in the period" />
        <x-admin.kpi label="Balance due" :value="$eur($kpis['balance_due'])" icon="receipt" hint="Still to collect on these bookings" />
        <x-admin.kpi label="Partner sales" :value="$eur($kpis['partner_sales'])" icon="handshake" />
        <x-admin.kpi label="Commission granted" :value="$eur($kpis['commission_granted'])" icon="star" hint="Customer price − partner price" />
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.75fr)_minmax(0,1fr)]">
        <x-admin.card title="Revenue by month" subtitle="Stacked by service">
            <div class="h-72"><canvas role="img" aria-label="Revenue by month and service" data-report-chart="bar" data-chart-config='@json($charts['revenue'])'></canvas></div>
        </x-admin.card>
        <x-admin.card title="Nationality mix" subtitle="Bookings per nationality group">
            <div class="h-72"><canvas role="img" aria-label="Bookings per nationality group" data-report-chart="doughnut" data-chart-config='@json($charts['nationalities'])'></canvas></div>
        </x-admin.card>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 2xl:grid-cols-2">
        @foreach ($tables as $key => $table)
            <x-admin.card :title="$table['title']" :padding="false">
                <x-slot:actions><a href="{{ route('admin.reports.export', ['table' => $key, ...$query]) }}" class="btn-ghost btn-sm"><x-admin.icon name="download" size="16" /> CSV</a></x-slot:actions>
                <div class="max-h-[420px] overflow-auto">
                    <table class="admin-table min-w-[520px]">
                        <thead class="sticky top-0 bg-white"><tr>@foreach ($table['headings'] as $index => $heading)<th scope="col" @class(['text-right' => is_int($table['rows'][0][$index] ?? null) || is_float($table['rows'][0][$index] ?? null)])>{{ $heading }}</th>@endforeach</tr></thead>
                        <tbody>
                            @forelse ($table['rows'] as $row)
                                <tr>
                                    @foreach ($row as $index => $value)
                                        <td @class(['text-right tabular-nums' => is_int($value) || is_float($value)])>{{ in_array($index, $table['money'], true) ? $eur2($value) : $value }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($table['headings']) }}" class="py-6 text-center text-ink-500">No data for these filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        @endforeach
    </div>
</x-admin.layout>
