@php use Illuminate\Support\Number; @endphp
<x-admin.layout title="Overview">
    <x-admin.page-header title="Overview" :subtitle="now()->format('l j M Y').' · All amounts in EUR'">
        @if ($finance)
            <x-slot:actions>
                <a href="{{ route('admin.bookings.export') }}" class="btn-primary"><x-admin.icon name="download" size="18" /> Export report</a>
                <a href="{{ route('admin.bookings.index') }}" class="btn-cta">View bookings</a>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    @if ($finance)
        <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            <x-admin.kpi label="Bookings today" :value="Number::format($kpis['bookings_today'])" icon="calendar" />
            <x-admin.kpi label="Revenue this month" :value="Number::currency($kpis['revenue_month'], 'EUR', 'en', 0)" icon="euro"
                         :hint="'Last month '.Number::currency($kpis['revenue_last_month'], 'EUR', 'en', 0)" />
            <x-admin.kpi label="Balance due" :value="Number::currency($kpis['balance_due'], 'EUR', 'en', 0)" icon="wallet" hint="Deposit paid, balance outstanding" />
            <x-admin.kpi label="Partner sales" :value="Number::currency($kpis['partner_sales_month'], 'EUR', 'en', 0)" icon="handshake" hint="This month" />
        </div>
    @endif

    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.75fr)_minmax(0,1fr)]">
        <x-admin.card title="Bookings" subtitle="Bookings created and deposits collected per day, last 30 days">
            <div class="h-64 sm:h-72">
                @php
                    $chartConfig = [
                        'labels' => $chart['labels'],
                        'datasets' => array_values(array_filter([
                            ['label' => 'Bookings', 'data' => $chart['bookings'], 'color' => '#101a5c'],
                            $finance ? ['label' => 'Deposits (EUR)', 'data' => $chart['deposits'], 'color' => '#c0550a', 'axis' => 'y1'] : null,
                        ])),
                    ];
                @endphp
                <canvas aria-label="Bookings and deposits chart" role="img" data-chart='@json($chartConfig)'></canvas>
            </div>
            <div class="mt-4 flex gap-5 text-sm text-ink-700">
                <span class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-primary-700"></span> Bookings</span>
                @if ($finance)<span class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-cta-600"></span> Deposits</span>@endif
            </div>
        </x-admin.card>

        <x-admin.card title="Needs attention">
            @if ($finance)
                <x-slot:actions><span class="chip border-brand-100 bg-brand-100 text-brand-700">{{ count($tasks) }} {{ str('task')->plural(count($tasks)) }}</span></x-slot:actions>
            @endif
            <ul class="divide-y divide-ink-100">
                @forelse ($tasks as $task)
                    <li class="flex items-center gap-3 py-3">
                        <span @class([
                            'grid size-10 shrink-0 place-items-center rounded-full',
                            'bg-warning/10 text-warning' => $task['tone'] === 'warning',
                            'bg-error/10 text-error' => $task['tone'] === 'error',
                            'bg-primary-100 text-primary-700' => $task['tone'] === 'primary',
                        ])><x-admin.icon :name="$task['icon']" size="18" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-ink-900">{{ $task['title'] }}</p>
                            @if ($task['detail'])<p class="truncate text-xs text-ink-500">{{ $task['detail'] }}</p>@endif
                        </div>
                        <a href="{{ $task['url'] }}" class="btn-link">{{ $task['action'] }}</a>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm text-ink-500">{{ $finance ? 'All clear. Nothing needs attention right now.' : 'Sales tasks are visible to agents and super admins.' }}</li>
                @endforelse
            </ul>
        </x-admin.card>
    </div>

    @if ($finance)
        <x-admin.card title="Recent bookings" class="mt-4" :padding="false">
            <x-slot:actions><a href="{{ route('admin.bookings.index') }}" class="btn-link">All bookings <x-admin.icon name="chevron-right" size="16" /></a></x-slot:actions>
            <div class="overflow-x-auto">
                <table class="admin-table min-w-[720px]">
                    <thead>
                        <tr>
                            <th scope="col">Reference</th><th scope="col">Service</th><th scope="col">Booker</th><th scope="col">Date</th>
                            <th scope="col" class="text-right">Total</th><th scope="col" class="text-right">Paid</th><th scope="col" class="text-right">Balance</th><th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recent as $booking)
                            <tr class="hover:bg-sand-50">
                                <td><a href="{{ route('admin.bookings.show', $booking) }}" class="font-semibold text-primary-700 hover:underline">{{ $booking->reference }}</a></td>
                                <td>{{ $booking->service_type->label() }}</td>
                                <td>{{ $booking->partner?->company ?? $booking->lead_name }}</td>
                                <td>{{ ($booking->start_date ?? $booking->check_in)?->format('j M Y') ?? '—' }}</td>
                                <td class="text-right tabular-nums">{{ Number::currency($booking->total, 'EUR', 'en') }}</td>
                                <td class="text-right tabular-nums">{{ Number::currency($booking->paidAmount(), 'EUR', 'en') }}</td>
                                <td class="text-right tabular-nums">{{ Number::currency($booking->dueAmount(), 'EUR', 'en') }}</td>
                                <td><x-admin.status-chip :status="$booking->payment_status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="py-8 text-center text-ink-500">No bookings yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.card>
    @endif
</x-admin.layout>
