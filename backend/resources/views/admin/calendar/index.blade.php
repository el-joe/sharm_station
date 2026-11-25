@php
    $cellClasses = [
        'free' => 'bg-white hover:bg-primary-100 cursor-pointer',
        'blocked' => 'bg-ink-300/70 text-ink-700 hover:bg-ink-300 cursor-pointer',
        'held' => 'bg-brand-100 text-brand-700',
        'booked' => 'bg-primary-700 text-white',
    ];
    $query = fn (array $extra) => route('admin.calendar.index', array_filter(['building_id' => $buildingId, ...$extra]));
@endphp
<x-admin.layout title="Accommodation calendar">
    <x-admin.page-header title="Calendar" :breadcrumbs="['Accommodation' => null, 'Calendar' => null]" subtitle="Room occupancy by night. Select free nights to block them, click a blocked night to free it.">
        <x-slot:actions>
            <a href="{{ $query(['month' => $month->subMonth()->format('Y-m')]) }}" class="btn-ghost" aria-label="Previous month"><x-admin.icon name="chevron-left" size="18" /></a>
            <span class="min-w-36 text-center font-semibold">{{ $month->format('F Y') }}</span>
            <a href="{{ $query(['month' => $month->addMonth()->format('Y-m')]) }}" class="btn-ghost" aria-label="Next month"><x-admin.icon name="chevron-right" size="18" /></a>
            <a href="{{ $query(['month' => now()->format('Y-m')]) }}" class="btn-primary">Today</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="GET" action="{{ route('admin.calendar.index') }}" class="card mb-4 flex flex-wrap items-end gap-3 p-4" aria-label="Filters">
        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
        <x-admin.select name="building_id" label="Building" :options="$buildings" :selected="$buildingId" placeholder="All buildings" data-s2-clear onchange="this.form.submit()" span="w-full sm:w-72" />
        <noscript><button type="submit" class="btn-primary">Show</button></noscript>
        <div class="ml-auto flex flex-wrap gap-4 text-xs text-ink-700" aria-label="Legend">
            <span class="flex items-center gap-2"><span class="size-3 rounded border border-ink-300 bg-white"></span> Free ({{ $grid['totals']['free'] }})</span>
            <span class="flex items-center gap-2"><span class="size-3 rounded bg-brand-100"></span> Held ({{ $grid['totals']['held'] }})</span>
            <span class="flex items-center gap-2"><span class="size-3 rounded bg-primary-700"></span> Booked ({{ $grid['totals']['booked'] }})</span>
            <span class="flex items-center gap-2"><span class="size-3 rounded bg-ink-300/70"></span> Blocked ({{ $grid['totals']['blocked'] }})</span>
        </div>
    </form>

    @if ($canManageBookings && $grid['holds'] !== [])
        <x-admin.card title="Rooms on hold" subtitle="Unpaid bookings holding rooms until their payment deadline. Releasing a hold cancels the booking and frees its nights now." class="mb-4">
            <ul class="divide-y divide-ink-100 text-sm">
                @foreach ($grid['holds'] as $hold)
                    <li class="flex flex-wrap items-center gap-3 py-2">
                        <a href="{{ route('admin.bookings.show', $hold['id']) }}" class="font-semibold text-primary-700 hover:underline">{{ $hold['reference'] }}</a>
                        <span>{{ $hold['lead_name'] }}</span>
                        <span class="text-ink-500">{{ implode(', ', $hold['rooms']) }}</span>
                        @if ($hold['held_until'])<span class="text-ink-500">held until {{ $hold['held_until']->format('j M H:i') }}</span>@endif
                        <form method="POST" action="{{ route('admin.bookings.release-hold', $hold['id']) }}" class="ml-auto" data-confirm="Release the hold of {{ $hold['reference'] }}? The booking is cancelled and its rooms become free." data-confirm-title="Release hold" data-confirm-ok="Release">
                            @csrf
                            <button type="submit" class="btn-ghost text-sm">Release hold</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </x-admin.card>
    @endif

    <x-admin.card :padding="false">
        <div class="border-b border-ink-100 px-4 py-3 text-sm text-ink-700" data-occupancy-hint aria-live="polite">Click a free night, then another night of the same room to block the range.@if ($canManageBookings) Drag a booking to another room or date to move it.@endif</div>
        @if ($grid['buildings'] === [])
            <p class="p-8 text-center text-sm text-ink-500">No rooms yet. <a href="{{ route('admin.rooms.create') }}" class="btn-link">Add a room</a></p>
        @else
            <div class="overflow-x-auto" data-occupancy data-url="{{ route('admin.calendar.block') }}" data-cell-classes='@json($cellClasses)'
                 @if ($canManageBookings) data-occupancy-move="{{ route('admin.calendar.move') }}" @endif>
                <table class="occupancy-grid w-max min-w-full text-xs">
                    <thead>
                        <tr>
                            <th scope="col" class="room-cell min-w-44 px-3 py-2 text-left text-ink-500">Room</th>
                            @foreach ($grid['days'] as $day)
                                <th scope="col" class="day-cell font-medium {{ $day['today'] ? 'text-cta-600' : ($day['weekend'] ? 'text-ink-900' : 'text-ink-500') }}" title="{{ $day['date'] }}">
                                    <span class="block text-[10px] uppercase">{{ substr($day['weekday'], 0, 2) }}</span>{{ $day['day'] }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    @foreach ($grid['buildings'] as $building)
                        <tbody>
                            <tr><th scope="rowgroup" colspan="{{ count($grid['days']) + 1 }}" class="bg-ink-100 px-3 py-1.5 text-left text-xs font-semibold tracking-wide text-ink-700 uppercase"><span class="sticky left-3">{{ $building['name'] }}</span></th></tr>
                            @foreach ($building['rooms'] as $room)
                                <tr data-room-row="{{ $room['id'] }}">
                                    <th scope="row" class="room-cell px-3 py-1 text-left font-medium text-ink-900">
                                        <a href="{{ route('admin.rooms.calendar', [$room['id'], 'month' => $month->format('Y-m')]) }}" class="hover:underline">{{ $room['name'] }}</a>
                                        @if ($room['number'])<span class="text-ink-500">#{{ $room['number'] }}</span>@endif
                                        @if ($room['inactive'])<span class="ml-1 text-[10px] text-ink-500 uppercase">inactive</span>@endif
                                    </th>
                                    @foreach ($room['cells'] as $index => $cell)
                                        @php($past = $grid['days'][$index]['past'])
                                        <td class="day-cell" data-drop-room="{{ $room['id'] }}" data-drop-date="{{ $cell['date'] }}">
                                            @if ($cell['booking'])
                                                @php($label = ucfirst($cell['status']).' · '.$cell['booking']['reference'].' · '.$cell['booking']['lead_name'])
                                                @if ($canManageBookings)
                                                    <a href="{{ route('admin.bookings.show', $cell['booking']['id']) }}" title="{{ $label }} (drag to move)" aria-label="{{ $room['name'] }} {{ $cell['date'] }}: {{ $label }}"
                                                       draggable="true" data-move-booking="{{ $cell['booking']['id'] }}" data-move-room="{{ $room['id'] }}" data-move-date="{{ $cell['date'] }}" data-move-check-in="{{ $cell['booking']['check_in'] }}"
                                                       class="flex h-9 items-center justify-center rounded-[6px] text-[10px] font-semibold {{ $cellClasses[$cell['status']] }}">{{ substr($cell['booking']['reference'], 3) }}</a>
                                                @else
                                                    <span title="{{ $label }}" class="flex h-9 items-center justify-center rounded-[6px] text-[10px] font-semibold {{ $cellClasses[$cell['status']] }}">{{ substr($cell['booking']['reference'], 3) }}</span>
                                                @endif
                                            @else
                                                <button type="button" data-cell data-room="{{ $room['id'] }}" data-date="{{ $cell['date'] }}" data-status="{{ $cell['status'] }}"
                                                        @disabled($past || $cell['status'] === 'held' || $cell['status'] === 'booked')
                                                        aria-label="{{ $room['name'] }} {{ $cell['date'] }}: {{ $cell['status'] }}"
                                                        @if ($cell['imported']) title="Closed by an imported calendar (iCal)" @endif
                                                        class="flex h-9 w-full items-center justify-center rounded-[6px] border border-ink-100 {{ $cellClasses[$cell['status']] }} {{ $past ? 'cursor-not-allowed opacity-40' : '' }}">
                                                    {!! $cell['status'] === 'blocked' ? '<span aria-hidden="true">✕</span>' : '' !!}
                                                </button>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
        @endif
    </x-admin.card>
</x-admin.layout>
