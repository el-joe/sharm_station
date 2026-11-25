@php
    $user = auth()->user();
    $finance = $user?->role?->canManagePrices() ?? false;
    $settings = $user?->role?->canManageSettings() ?? false;
    $reports = $user?->role?->canViewReports() ?? false;
    $manageUsers = $user?->role?->canManageUsers() ?? false;
    $isNile = request()->routeIs('admin.trips.*') && (request('type') === 'nile' || request()->route('trip')?->type?->value === 'nile');

    $groups = [
        ['label' => 'Overview', 'icon' => 'overview', 'items' => array_values(array_filter([
            ['label' => 'Dashboard', 'url' => route('admin.dashboard'), 'active' => request()->routeIs('admin.dashboard')],
            $reports ? ['label' => 'Reports', 'url' => route('admin.reports.index'), 'active' => request()->routeIs('admin.reports.*')] : null,
        ]))],
        ['label' => 'Catalogue', 'icon' => 'catalogue', 'items' => array_values(array_filter([
            ['label' => 'Trips', 'url' => route('admin.trips.index'), 'active' => request()->routeIs('admin.trips.*') && ! $isNile],
            ['label' => 'Nile trips', 'url' => route('admin.trips.index', ['type' => 'nile']), 'active' => $isNile],
            ['label' => 'Packages', 'url' => route('admin.packages.index'), 'active' => request()->routeIs('admin.packages.*')],
            ['label' => 'Add-ons', 'url' => route('admin.addons.index'), 'active' => request()->routeIs('admin.addons.*')],
            ['label' => 'Destinations', 'url' => route('admin.destinations.index'), 'active' => request()->routeIs('admin.destinations.*')],
            $finance ? ['label' => 'Nationality groups', 'url' => route('admin.nationality-groups.index'), 'active' => request()->routeIs('admin.nationality-groups.*')] : null,
        ]))],
        ['label' => 'Accommodation', 'icon' => 'bed', 'items' => [
            ['label' => 'Buildings', 'url' => route('admin.buildings.index'), 'active' => request()->routeIs('admin.buildings.*')],
            ['label' => 'Rooms', 'url' => route('admin.rooms.index'), 'active' => request()->routeIs('admin.rooms.*')],
            ['label' => 'Calendar', 'url' => route('admin.calendar.index'), 'active' => request()->routeIs('admin.calendar.*')],
        ]],
        ['label' => 'Sales', 'icon' => 'sales', 'items' => array_values(array_filter([
            $finance ? ['label' => 'Bookings', 'url' => route('admin.bookings.index'), 'active' => request()->routeIs('admin.bookings.*')] : null,
            $finance ? ['label' => 'Receipts review', 'url' => route('admin.receipts.index'), 'active' => request()->routeIs('admin.receipts.*')] : null,
            $finance ? ['label' => 'Payments', 'url' => route('admin.payments.index'), 'active' => request()->routeIs('admin.payments.*')] : null,
            $finance ? ['label' => 'Customers', 'url' => route('admin.customers.index'), 'active' => request()->routeIs('admin.customers.*')] : null,
            $finance ? ['label' => 'Partners', 'url' => route('admin.partners.index'), 'active' => request()->routeIs('admin.partners.*')] : null,
            $finance ? ['label' => 'Coupons', 'url' => route('admin.coupons.index'), 'active' => request()->routeIs('admin.coupons.*')] : null,
            $finance ? ['label' => 'Contact messages', 'url' => route('admin.contact-messages.index'), 'active' => request()->routeIs('admin.contact-messages.*')] : null,
            ['label' => 'Google reviews', 'url' => route('admin.google-reviews.index'), 'active' => request()->routeIs('admin.google-reviews.*')],
        ]))],
        ['label' => 'Transfers', 'icon' => 'car', 'items' => $finance ? [
            ['label' => 'Requests', 'url' => route('admin.transfers.requests.index'), 'active' => request()->routeIs('admin.transfers.requests.*')],
            ['label' => 'Hubs', 'url' => route('admin.transfers.hubs.index'), 'active' => request()->routeIs('admin.transfers.hubs.*')],
            ['label' => 'Vehicles', 'url' => route('admin.transfers.vehicles.index'), 'active' => request()->routeIs('admin.transfers.vehicles.*')],
            ['label' => 'Fixed routes', 'url' => route('admin.transfers.routes.index'), 'active' => request()->routeIs('admin.transfers.routes.*')],
            ['label' => 'Pricing simulator', 'url' => route('admin.transfers.simulator'), 'active' => request()->routeIs('admin.transfers.simulator')],
        ] : []],
        ['label' => 'Content', 'icon' => 'pen', 'items' => [
            ['label' => 'Pages, blog and FAQ', 'url' => route('admin.content.index'), 'active' => request()->routeIs('admin.content.*')],
            ['label' => 'AI suggestions', 'url' => route('admin.ai-suggestions.index'), 'active' => request()->routeIs('admin.ai-suggestions.*')],
        ]],
        ['label' => 'Settings', 'icon' => 'settings', 'items' => array_values(array_filter([
            $settings ? ['label' => 'General', 'url' => route('admin.settings.edit'), 'active' => request()->routeIs('admin.settings.*')] : null,
            $finance ? ['label' => 'Commissions', 'url' => route('admin.commissions.edit'), 'active' => request()->routeIs('admin.commissions.*')] : null,
            $finance ? ['label' => 'Payment methods', 'url' => route('admin.payment-methods.index'), 'active' => request()->routeIs('admin.payment-methods.*')] : null,
            $finance ? ['label' => 'Currencies', 'url' => route('admin.currencies.index'), 'active' => request()->routeIs('admin.currencies.*')] : null,
            $settings ? ['label' => 'MCP access', 'url' => route('admin.mcp.index'), 'active' => request()->routeIs('admin.mcp.*')] : null,
            $settings ? ['label' => 'Notifications', 'url' => route('admin.notifications.index'), 'active' => request()->routeIs('admin.notifications.*')] : null,
            ['label' => 'Security (2FA)', 'url' => route('admin.two-factor.show'), 'active' => request()->routeIs('admin.two-factor.*')],
            $manageUsers ? ['label' => 'Users and roles', 'url' => route('admin.users.index'), 'active' => request()->routeIs('admin.users.*')] : null,
            $manageUsers ? ['label' => 'Activity log', 'url' => route('admin.activity.index'), 'active' => request()->routeIs('admin.activity.*')] : null,
        ]))],
    ];
@endphp
<aside id="admin-sidebar" class="admin-sidebar fixed inset-y-0 left-0 z-40 flex w-66 -translate-x-full flex-col bg-brand-navy-900 text-white transition-[width,transform] duration-200 lg:translate-x-0" aria-label="Main navigation">
    <div class="flex h-16 shrink-0 items-center justify-between gap-2 px-4">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5">
            <span class="grid size-9 place-items-center rounded-full bg-gold-500 font-display text-lg text-brand-navy-900">S</span>
            <span class="nav-text leading-tight">
                <span class="block font-display text-base">Sharm Station</span>
                <span class="block text-[11px] tracking-wide text-gold-500 uppercase">Back office</span>
            </span>
        </a>
        <button type="button" class="sidebar-toggle hidden rounded-control p-1.5 text-white/70 hover:bg-white/10 hover:text-white lg:inline-flex" data-sidebar-collapse aria-expanded="true" aria-label="Collapse sidebar">
            <x-admin.icon name="chevron-left" class="transition-transform" />
        </button>
        <button type="button" class="rounded-control p-1.5 text-white/70 hover:bg-white/10 lg:hidden" data-drawer-close aria-label="Close menu">
            <x-admin.icon name="x" />
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 pb-6">
        <ul class="space-y-1">
            @foreach ($groups as $index => $group)
                @if (isset($group['items']))
                    @continue(count($group['items']) === 0)
                    @php($open = collect($group['items'])->contains('active', true))
                    <li>
                        <button type="button" data-nav-group aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="nav-group-{{ $index }}"
                                class="flex w-full items-center gap-3 rounded-control px-3 py-2.5 text-[15px] font-medium text-white/85 hover:bg-white/10 hover:text-white"
                                title="{{ $group['label'] }}">
                            <x-admin.icon :name="$group['icon']" />
                            <span class="nav-text flex-1 text-left">{{ $group['label'] }}</span>
                            <x-admin.icon name="chevron-down" size="16" class="nav-chevron text-white/60" />
                        </button>
                        <ul id="nav-group-{{ $index }}" class="nav-group-items mt-1 space-y-0.5 pl-8 {{ $open ? '' : 'hidden' }}">
                            @foreach ($group['items'] as $item)
                                <li>
                                    <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif
                                       class="block rounded-control px-3 py-2 text-sm {{ $item['active'] ? 'bg-primary-700 font-semibold text-white' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                        {{ $item['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @else
                    <li>
                        <a href="{{ route($group['route'], $group['params'] ?? []) }}" @if ($group['active']) aria-current="page" @endif title="{{ $group['label'] }}"
                           class="flex items-center gap-3 rounded-control px-3 py-2.5 text-[15px] font-medium {{ $group['active'] ? 'bg-primary-700 text-white' : 'text-white/85 hover:bg-white/10 hover:text-white' }}">
                            <x-admin.icon :name="$group['icon']" />
                            <span class="nav-text">{{ $group['label'] }}</span>
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
    </nav>
</aside>
