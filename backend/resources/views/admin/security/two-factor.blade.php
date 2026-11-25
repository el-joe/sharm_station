<x-admin.layout title="Two-factor authentication">
    <x-admin.page-header title="Two-factor authentication" :breadcrumbs="['Settings' => null, 'Security' => null]" subtitle="Protect your back-office account with a code from an authenticator app (Google Authenticator, 1Password, Authy…)." />

    @if ($recoveryCodes)
        <x-admin.card title="Your recovery codes" subtitle="Each code works once if you lose your phone. They are shown only now: print them or store them in a password manager." class="mb-4 border-warning/50">
            <ul class="grid grid-cols-2 gap-2 font-mono text-sm sm:grid-cols-4" id="recovery-codes">
                @foreach ($recoveryCodes as $code)<li class="rounded-control bg-sand-50 px-3 py-2 text-center">{{ $code }}</li>@endforeach
            </ul>
            <button type="button" class="btn-ghost btn-sm mt-3" data-copy="#recovery-codes">Copy codes</button>
        </x-admin.card>
    @endif

    @if ($user->hasTwoFactorEnabled())
        <div class="grid gap-4 lg:grid-cols-2">
            <x-admin.card title="Status">
                <p class="text-sm"><x-admin.status-chip status="enabled" label="Enabled" /> since {{ $user->two_factor_confirmed_at?->format('j M Y H:i') }}</p>
                <p class="mt-2 text-sm text-ink-700">{{ $remainingCodes }} unused recovery {{ str('code')->plural($remainingCodes) }}.</p>
                <form method="POST" action="{{ route('admin.two-factor.recovery-codes') }}" class="mt-4 flex flex-wrap items-end gap-3" novalidate>
                    @csrf
                    <x-admin.input name="code" label="Current code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" span="w-40" />
                    <button type="submit" class="btn-ghost">New recovery codes</button>
                </form>
            </x-admin.card>
            <x-admin.card title="Turn off">
                @if ($required)
                    <p class="text-sm text-ink-700">Two-factor authentication is required for super admins. Another super admin can reset it if you lose your phone.</p>
                @else
                    <form method="POST" action="{{ route('admin.two-factor.disable') }}" class="flex flex-wrap items-end gap-3" data-confirm="Turn off two-factor authentication?" data-confirm-ok="Turn off" novalidate>
                        @csrf @method('DELETE')
                        <x-admin.input name="code" label="Current code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" span="w-40" />
                        <button type="submit" class="btn-danger">Turn off 2FA</button>
                    </form>
                @endif
            </x-admin.card>
        </div>
    @else
        @if ($required)
            <p class="mb-4 rounded-control border border-warning/40 bg-warning/10 px-4 py-3 text-sm text-[#8a5f00]">Two-factor authentication is required for super admins. Set it up to continue.</p>
        @endif
        <x-admin.card title="Set up" subtitle="1. Scan the QR code with your authenticator app. 2. Enter the 6-digit code it shows.">
            <div class="grid gap-6 md:grid-cols-[220px_minmax(0,1fr)]">
                <div class="rounded-card border border-ink-100 bg-white p-2" aria-label="QR code">{!! $qrCode !!}</div>
                <div>
                    <p class="text-sm text-ink-700">Can't scan it? Enter this key manually:</p>
                    <p class="mt-1 flex items-center gap-2"><code id="two-factor-secret" class="rounded-control bg-sand-50 px-3 py-1.5 font-mono text-sm tracking-wider">{{ trim(chunk_split($secret, 4, ' ')) }}</code>
                        <button type="button" class="btn-link" data-copy-text="{{ $secret }}">Copy</button></p>
                    <form method="POST" action="{{ route('admin.two-factor.enable') }}" class="mt-5 flex flex-wrap items-end gap-3" novalidate>
                        @csrf
                        <x-admin.input name="code" label="Code from the app" inputmode="numeric" autocomplete="one-time-code" maxlength="7" span="w-44" autofocus />
                        <button type="submit" class="btn-cta">Turn on 2FA</button>
                    </form>
                </div>
            </div>
        </x-admin.card>
    @endif
</x-admin.layout>
