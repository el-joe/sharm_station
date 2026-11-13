@php
    use App\Enums\TransferStatus;
    $title = 'Transfer '.($transfer->booking?->reference ?? '#'.$transfer->id);
@endphp
<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :breadcrumbs="['Transfers' => null, 'Requests' => route('admin.transfers.requests.index'), $title => null]"
                         :subtitle="($transfer->hub?->name ?? '').' → '.$transfer->destination_name.' · '.$transfer->pickup_at->format('l j M Y H:i')">
        <x-slot:actions>
            <x-admin.status-chip :status="$transfer->status" class="text-sm" />
            @if ($transfer->booking)<a href="{{ route('admin.bookings.show', $transfer->booking) }}" class="btn-primary">Open booking</a>@endif
        </x-slot:actions>
    </x-admin.page-header>
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-4">
            <x-admin.card title="Ride">
                <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm md:grid-cols-3">
                    <div><dt class="text-ink-500">Customer</dt><dd class="font-medium">{{ $transfer->booking?->lead_name ?? '—' }} · {{ $transfer->booking?->lead_phone }}</dd></div>
                    <div><dt class="text-ink-500">Flight</dt><dd class="font-medium">{{ $transfer->flight_number ?? '—' }}</dd></div>
                    <div><dt class="text-ink-500">Direction</dt><dd class="font-medium">{{ $transfer->direction->label() }}</dd></div>
                    <div><dt class="text-ink-500">Vehicle</dt><dd class="font-medium">{{ $transfer->vehicleType?->name ?? '—' }}</dd></div>
                    <div><dt class="text-ink-500">Passengers / luggage</dt><dd class="font-medium">{{ $transfer->passengers }} / {{ $transfer->luggage }}</dd></div>
                    <div><dt class="text-ink-500">Return</dt><dd class="font-medium">{{ $transfer->round_trip ? $transfer->return_at?->format('j M Y H:i') : 'One way' }}</dd></div>
                    <div><dt class="text-ink-500">Distance</dt><dd class="font-medium">{{ (float) $transfer->distance_km }} km · {{ $transfer->duration_minutes }} min ({{ $transfer->distance_provider }})</dd></div>
                    <div><dt class="text-ink-500">Extras</dt><dd class="font-medium">{{ implode(', ', $transfer->extras ?? []) ?: '—' }}</dd></div>
                    <div><dt class="text-ink-500">Price</dt><dd class="font-bold">€{{ number_format((float) $transfer->price, 2) }}</dd></div>
                </dl>
                @if ($transfer->needs_review)
                    <p class="mt-4 rounded-control bg-warning/10 p-3 text-sm text-[#8a5f00]">The distance is an offline estimate (no maps service): check the route before confirming.</p>
                @endif
            </x-admin.card>
            <x-admin.card title="Pricing breakdown" :padding="false">
                <div class="overflow-x-auto"><table class="admin-table">
                    <thead><tr><th scope="col">Leg</th><th scope="col">Method</th><th scope="col" class="text-right">Details</th><th scope="col" class="text-right">Price</th></tr></thead>
                    <tbody>
                        @foreach ($transfer->breakdown ?? [] as $leg)
                            @php($b = $leg['breakdown'] ?? [])
                            <tr>
                                <td>{{ ucfirst($leg['leg'] ?? '') }}</td>
                                <td>{{ str($b['method'] ?? '')->headline() }}</td>
                                <td class="text-right text-xs text-ink-700">
                                    @if (($b['method'] ?? '') === 'distance')
                                        base €{{ $b['base_fare'] }} + distance €{{ $b['distance_charge'] }} + waiting €{{ $b['waiting'] }} + surcharge €{{ $b['surcharge'] }} × {{ $b['multiplier'] }} (min €{{ $b['minimum_fare'] }}) + fees €{{ $b['fees'] }}
                                    @else
                                        fixed €{{ $b['fixed_price'] ?? '' }} × {{ $b['multiplier'] ?? 1 }}
                                    @endif
                                </td>
                                <td class="text-right tabular-nums">€{{ number_format((float) ($leg['price'] ?? 0), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
            </x-admin.card>
        </div>
        <aside>
            <x-admin.card title="Operations">
                <form method="POST" action="{{ route('admin.transfers.requests.update', $transfer) }}" class="space-y-3" novalidate>
                    @csrf @method('PATCH')
                    <x-admin.select name="status" label="Status" :options="TransferStatus::options()" :selected="$transfer->status" :select2="false" span="" />
                    <x-admin.input name="driver_name" label="Driver name" :value="$transfer->driver_name" span="" hint="Required to confirm." />
                    <x-admin.input name="driver_phone" label="Driver phone" :value="$transfer->driver_phone" span="" />
                    <x-admin.input name="car_type" label="Car" :value="$transfer->car_type" span="" />
                    <x-admin.input name="car_plate" label="Plate" :value="$transfer->car_plate" span="" />
                    <x-admin.textarea name="admin_notes" label="Internal notes" :value="$transfer->admin_notes" :rows="2" span="" />
                    <button type="submit" class="btn-cta w-full">Save</button>
                </form>
            </x-admin.card>
        </aside>
    </div>
</x-admin.layout>
