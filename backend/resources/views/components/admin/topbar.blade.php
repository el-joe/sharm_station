@php($user = auth()->user())
<header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-ink-100 bg-white px-4 sm:px-6 lg:px-8">
    <button type="button" class="-ml-1 rounded-control p-2 text-ink-700 hover:bg-ink-100 lg:hidden" data-drawer-open aria-label="Open menu">
        <x-admin.icon name="menu" />
    </button>

    <form action="{{ $user?->role?->canManagePrices() ? route('admin.bookings.index') : route('admin.trips.index') }}" method="GET" role="search" class="relative hidden max-w-sm flex-1 sm:block">
        <x-admin.icon name="search" size="18" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-ink-500" />
        <label for="global-search" class="sr-only">Search</label>
        <input id="global-search" name="q" type="search" value="{{ request('q') }}" placeholder="Search bookings, customers, trips"
               class="h-10 w-full rounded-control border-0 bg-ink-100 pr-3 pl-10 text-sm placeholder:text-ink-500 focus:ring-2 focus:ring-primary-100 focus:outline-none">
    </form>

    <div class="ml-auto flex items-center gap-2 sm:gap-3">
        <a href="{{ $user?->role?->canManagePrices() ? route('admin.bookings.index', ['status' => 'awaiting_receipt_review']) : route('admin.dashboard') }}" class="relative rounded-control p-2 text-ink-700 hover:bg-ink-100" aria-label="Notifications">
            <x-admin.icon name="bell" />
            <span class="absolute top-2 right-2 size-2 rounded-full bg-cta-600"></span>
        </a>

        <div class="relative hidden sm:block">
            <button type="button" data-dropdown aria-expanded="false" aria-controls="locale-menu" class="flex h-10 items-center gap-2 rounded-control border border-ink-300 px-3 text-sm font-medium text-ink-900 hover:bg-ink-100">
                <x-admin.icon name="globe" size="18" /> {{ strtoupper(app()->getLocale()) }} <x-admin.icon name="chevron-down" size="14" />
            </button>
            <div id="locale-menu" class="card absolute right-0 mt-2 hidden w-40 p-1 text-sm shadow-lg">
                <p class="px-3 py-2 text-xs text-ink-500">Content languages are edited per field (EN / IT tabs).</p>
            </div>
        </div>

        <div class="relative">
            <button type="button" data-dropdown aria-expanded="false" aria-controls="profile-menu" class="flex items-center gap-2 rounded-control p-1 hover:bg-ink-100">
                <span class="grid size-9 place-items-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700">{{ str($user?->name)->explode(' ')->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}</span>
                <span class="hidden text-left leading-tight md:block">
                    <span class="block text-sm font-medium text-ink-900">{{ $user?->name }}</span>
                    <span class="block text-xs text-ink-500">{{ $user?->role?->label() }}</span>
                </span>
                <x-admin.icon name="chevron-down" size="16" class="hidden text-ink-500 md:block" />
            </button>
            <div id="profile-menu" class="card absolute right-0 mt-2 hidden w-56 p-1 shadow-lg">
                <p class="truncate px-3 py-2 text-xs text-ink-500">{{ $user?->email }}</p>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2 rounded-control px-3 py-2 text-sm text-ink-900 hover:bg-ink-100">
                        <x-admin.icon name="logout" size="18" /> Sign out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
