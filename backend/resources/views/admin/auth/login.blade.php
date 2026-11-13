<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in · Sharm Station Admin</title>
    @fonts(['inter', 'fraunces'])
    @vite(['resources/css/admin.css', 'resources/js/admin/app.js'])
</head>
<body class="min-h-screen bg-sand-50">
    <div class="grid min-h-screen lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
        <div class="flex flex-col justify-center bg-brand-navy-900 px-6 py-10 text-white sm:px-12 lg:px-14">
            <p class="font-display text-3xl sm:text-4xl">Sharm Station Viaggi</p>
            <p class="mt-3 max-w-md text-sm text-white/75 sm:text-base">Back office · trips, Nile cruises, packages, transfers and accommodation</p>
        </div>

        <main class="flex items-center justify-center px-4 py-10 sm:px-8">
            <div class="card w-full max-w-[330px] p-7 shadow-[0_12px_40px_rgba(11,16,56,0.10)]">
                <h1 class="text-2xl text-ink-900">Sign in</h1>
                <p class="mt-2 text-sm text-ink-500">Use your staff account.</p>

                <form method="POST" action="{{ route('admin.login.store') }}" class="mt-5 space-y-4" novalidate>
                    @csrf
                    <x-admin.input name="email" type="email" label="Email" autocomplete="username" required autofocus span="" />
                    <x-admin.input name="password" type="password" label="Password" autocomplete="current-password" required span="" />

                    <div class="flex items-center justify-between text-xs">
                        <label class="flex items-center gap-1.5 text-ink-700">
                            <input type="checkbox" name="remember" value="1" class="size-3.5 rounded border-ink-300 accent-primary-700" @checked(old('remember'))>
                            Remember me
                        </label>
                        <span class="font-semibold text-primary-700" title="Ask a super admin to reset your password">Forgot password?</span>
                    </div>

                    <button type="submit" class="btn-cta w-full">Sign in</button>
                </form>
                <p class="mt-4 text-[11px] text-ink-500">Two-factor authentication for Super Admin is planned (SRS 14.2).</p>
            </div>
        </main>
    </div>
</body>
</html>
