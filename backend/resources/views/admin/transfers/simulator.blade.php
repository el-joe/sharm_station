@php
    $input = request()->all();
    $eur = fn ($amount) => '€'.number_format((float) $amount, 2);
@endphp
<x-admin.layout title="Pricing simulator">
    <x-admin.page-header title="Pricing simulator" :breadcrumbs="['Transfers' => null, 'Simulator' => null]" subtitle="Test any route with the live hub settings. Nothing is saved." />
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_420px]">
        <form method="GET" action="{{ route('admin.transfers.simulator') }}" class="card space-y-4 p-5 sm:p-6" novalidate>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-6">
                <x-admin.select name="hub_id" label="Hub" :options="$hubs" :selected="$input['hub_id'] ?? null" placeholder="Choose a hub" span="md:col-span-3" />
                <x-admin.select name="vehicle_type_id" label="Vehicle" :options="$vehicles" :selected="$input['vehicle_type_id'] ?? null" placeholder="Choose a vehicle" span="md:col-span-3" />
                <x-admin.input name="destination[name]" label="Destination name" :value="$input['destination']['name'] ?? 'Test destination'" span="md:col-span-2" />
                <x-admin.input name="destination[lat]" label="Latitude" type="number" step="0.0000001" :value="$input['destination']['lat'] ?? null" span="md:col-span-2" />
                <x-admin.input name="destination[lng]" label="Longitude" type="number" step="0.0000001" :value="$input['destination']['lng'] ?? null" span="md:col-span-2" />
                <x-admin.input name="pickup_at" label="Pickup (YYYY-MM-DD HH:MM)" :value="$input['pickup_at'] ?? now()->addDay()->setTime(10, 0)->format('Y-m-d H:i')" span="md:col-span-2" />
                <x-admin.input name="passengers" label="Passengers" type="number" min="1" :value="$input['passengers'] ?? 2" span="md:col-span-1" />
                <x-admin.input name="luggage" label="Luggage" type="number" min="0" :value="$input['luggage'] ?? 2" span="md:col-span-1" />
                <x-admin.input name="waiting_minutes" label="Waiting (min)" type="number" min="0" :value="$input['waiting_minutes'] ?? 0" span="md:col-span-2" />
                <x-admin.switch name="round_trip" label="Round trip" :checked="(bool) ($input['round_trip'] ?? false)" span="md:col-span-2" />
                <x-admin.input name="return_at" label="Return (YYYY-MM-DD HH:MM)" :value="$input['return_at'] ?? null" span="md:col-span-2" />
                <x-admin.input name="extras[]" label="Extra code (optional)" :value="($input['extras'][0] ?? null)" placeholder="child_seat" span="md:col-span-2" />
            </div>
            <button type="submit" class="btn-cta">Calculate</button>
        </form>

        <x-admin.card title="Result">
            @if ($quote === null)
                <p class="text-sm text-ink-500">Fill in the form and calculate.</p>
            @else
                @if (! $quote->isValid())
                    <div role="alert" class="mb-4 space-y-1 rounded-control bg-error/10 px-4 py-3 text-sm text-error">
                        @foreach (collect($quote->errors)->flatten() as $message)<p>{{ $message }}</p>@endforeach
                    </div>
                @endif
                @isset($quote->context['distance_km'])
                    <p class="mb-3 text-sm text-ink-700">{{ $quote->context['distance_km'] }} km · {{ $quote->context['duration_minutes'] }} min ({{ $quote->context['distance_provider'] }}{{ $quote->context['distance_estimated'] ? ', estimated' : '' }})</p>
                @endisset
                <table class="admin-table">
                    <tbody>
                        @foreach ($quote->lines as $line)
                            <tr>
                                <td>{{ $line->label }}
                                    @if (isset($line->meta['breakdown']) && $line->meta['breakdown']['method'] === 'distance')
                                        @php($b = $line->meta['breakdown'])
                                        <div class="text-xs text-ink-500">B {{ $eur($b['base_fare']) }} + Σd·R {{ $eur($b['distance_charge']) }} + W {{ $eur($b['waiting']) }} + S {{ $eur($b['surcharge']) }} × V {{ $b['multiplier'] }} = {{ $eur($b['subtotal']) }}; M {{ $eur($b['minimum_fare']) }}{{ $b['minimum_applied'] ? ' applied' : '' }}; F {{ $eur($b['fees']) }}</div>
                                        @foreach ($b['tiers'] as $tier)<div class="text-xs text-ink-500">{{ $tier['km'] }} km × €{{ $tier['rate'] }} = {{ $eur($tier['amount']) }}</div>@endforeach
                                    @elseif (isset($line->meta['breakdown']))
                                        <div class="text-xs text-ink-500">Fixed route #{{ $line->meta['breakdown']['fixed_route_id'] }}</div>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums">{{ $eur($line->total) }}</td>
                            </tr>
                        @endforeach
                        <tr><td class="font-semibold">Total</td><td class="text-right text-lg font-bold tabular-nums">{{ $eur($quote->total) }}</td></tr>
                        <tr><td class="text-ink-500">Deposit ({{ $quote->depositPercent }}%)</td><td class="text-right tabular-nums">{{ $eur($quote->depositAmount) }}</td></tr>
                    </tbody>
                </table>
            @endif
        </x-admin.card>
    </div>
</x-admin.layout>
