@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-sidebar="expanded">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}Sharm Station Admin</title>
    @fonts(['inter', 'fraunces'])
    @vite(['resources/css/admin.css', 'resources/js/admin/app.js'])
</head>
<body class="min-h-screen">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[200] focus:rounded-control focus:bg-white focus:px-4 focus:py-2">Skip to content</a>

    <x-admin.sidebar />
    <div id="sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-brand-navy-900/50 lg:hidden"></div>

    <div class="admin-main min-h-screen transition-[padding] lg:pl-66">
        <x-admin.topbar />
        <main id="main" class="mx-auto w-full max-w-[1272px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            {{ $slot }}
        </main>
    </div>

    <x-admin.flash />
    @stack('scripts')
</body>
</html>
