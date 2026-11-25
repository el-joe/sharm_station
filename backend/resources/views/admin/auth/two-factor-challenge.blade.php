<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Two-factor check · Sharm Station Admin</title>
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
                <h1 class="text-2xl text-ink-900">Two-factor check</h1>
                <p class="mt-2 text-sm text-ink-500">Enter the 6-digit code from your authenticator app.</p>

                <form method="POST" action="{{ route('admin.two-factor.challenge.store') }}" class="mt-5 space-y-4" novalidate>
                    @csrf
                    <x-admin.input name="code" label="Authentication code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" autofocus span="" />
                    <details class="text-sm" @if ($errors->has('recovery_code')) open @endif>
                        <summary class="cursor-pointer font-semibold text-primary-700">Use a recovery code</summary>
                        <div class="mt-3"><x-admin.input name="recovery_code" label="Recovery code" autocomplete="off" placeholder="XXXXX-XXXXX" span="" /></div>
                    </details>
                    <button type="submit" class="btn-cta w-full">Verify and sign in</button>
                </form>
                <p class="mt-4 text-xs"><a href="{{ route('admin.login') }}" class="btn-link">Back to sign in</a></p>
                            </div>
        </main>
    </div>
</body>
</html>
