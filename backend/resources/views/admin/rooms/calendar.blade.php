@php
    $dayClass = 'relative flex h-14 w-full flex-col items-start justify-between rounded-control border p-1.5 sm:h-20 text-left text-xs sm:p-2 sm:text-sm transition';
    $statusClasses = [
        'open' => 'border-ink-100 bg-white text-ink-900 hover:border-primary-500',
        'closed' => 'border-ink-300 bg-ink-100 text-ink-500 line-through hover:border-primary-500',
        'held' => 'border-brand-100 bg-brand-100 text-brand-700 cursor-not-allowed',
        'booked' => 'border-primary-100 bg-primary-100 text-primary-700 cursor-not-allowed',
    ];
@endphp
<x-admin.layout :title="'Calendar · '.$room->name">
    <x-admin.page-header :title="(string) $room->name" :subtitle="$room->building?->name.' · Availability calendar'"
                         :breadcrumbs="['Accommodation' => null, 'Rooms' => route('admin.rooms.index', ['building_id' => $room->building_id]), (string) $room->name => route('admin.rooms.edit', $room), 'Calendar' => null]">
        <x-slot:actions>
            <a href="{{ route('admin.rooms.calendar', [$room, 'month' => $month->subMonth()->format('Y-m')]) }}" class="btn-ghost" aria-label="Previous month"><x-admin.icon name="chevron-left" size="18" /></a>
            <span class="min-w-36 text-center font-semibold">{{ $month->format('F Y') }}</span>
            <a href="{{ route('admin.rooms.calendar', [$room, 'month' => $month->addMonth()->format('Y-m')]) }}" class="btn-ghost" aria-label="Next month"><x-admin.icon name="chevron-right" size="18" /></a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.card>
        <p class="mb-4 text-sm text-ink-700">Click an open or closed day to toggle it (maintenance, owner use). Booked and held nights are blocked by bookings.</p>
        <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold text-ink-500 sm:gap-2">
            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)<div class="py-1">{{ $weekday }}</div>@endforeach
        </div>
        <div class="mt-1 grid grid-cols-7 gap-1 sm:gap-2" data-room-calendar data-url="{{ route('admin.rooms.availability.update', $room) }}" data-day-class="{{ $dayClass }}">
            @foreach ($weeks as $week)
                @foreach ($week as $day)
                    @php($status = $day['status']->value)
                    @if (! $day['in_month'])
                        <div class="h-14 sm:h-20"></div>
                    @else
                        <button type="button" data-date="{{ $day['date'] }}" data-status="{{ $status }}" aria-pressed="{{ $status === 'closed' ? 'true' : 'false' }}"
                                aria-label="{{ \Carbon\CarbonImmutable::parse($day['date'])->format('j F Y') }}: {{ $status }}"
                                @disabled($day['past'] || in_array($status, ['held', 'booked'], true))
                                class="{{ $dayClass }} {{ $statusClasses[$status] }} {{ $day['past'] ? 'opacity-50' : '' }}">
                            <span class="font-semibold">{{ $day['day'] }}</span>
                            <span data-day-status class="text-[10px] font-medium no-underline sm:text-xs">{{ $status === 'open' ? '' : ucfirst($status) }}</span>
                        </button>
                    @endif
                @endforeach
            @endforeach
        </div>
        <div class="mt-4 flex flex-wrap gap-4 text-xs text-ink-700">
            <span class="flex items-center gap-2"><span class="size-3 rounded border border-ink-300 bg-white"></span> Open</span>
            <span class="flex items-center gap-2"><span class="size-3 rounded bg-ink-100"></span> Closed</span>
            <span class="flex items-center gap-2"><span class="size-3 rounded bg-brand-100"></span> Held</span>
            <span class="flex items-center gap-2"><span class="size-3 rounded bg-primary-100"></span> Booked</span>
        </div>
    </x-admin.card>
</x-admin.layout>
