@php
    use App\Enums\UserRole;
    $title = $user->exists ? $user->name : 'New user';
    $isSelf = $user->exists && $user->is(auth()->user());
    $roleOptions = collect(UserRole::staff())->mapWithKeys(fn ($role) => [$role->value => $role->label()])->all();
@endphp
<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :breadcrumbs="['Settings' => null, 'Users and roles' => route('admin.users.index'), $title => null]"
                         :subtitle="$user->exists ? $user->email.' · created '.$user->created_at?->format('j M Y') : null">
        <x-slot:actions><button type="submit" form="user-form" class="btn-cta">Save</button></x-slot:actions>
    </x-admin.page-header>
    <form id="user-form" method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" novalidate class="space-y-4">
        @csrf
        @if ($user->exists) @method('PUT') @endif
        <x-admin.form-section title="Account">
            <x-admin.input name="name" label="Name" :value="$user->name" required maxlength="255" autocomplete="off" />
            <x-admin.input name="email" label="Email" type="email" :value="$user->email" required maxlength="255" autocomplete="off" />
            <x-admin.input name="phone" label="Phone" :value="$user->phone" maxlength="40" />
            <x-admin.select name="role" label="Role" :options="$roleOptions" :selected="$user->role" :select2="false" :disabled="$isSelf ? 'disabled' : null" hint="{{ $isSelf ? 'You cannot change your own role.' : 'Editors manage content only; agents also sales and finance.' }}" />
            @if ($isSelf)<input type="hidden" name="role" value="{{ $user->role?->value }}">@endif
            @if ($isSelf)
                <input type="hidden" name="is_active" value="1">
            @else
                <x-admin.switch name="is_active" label="Active" description="Deactivated users cannot sign in" :checked="$user->isActive()" span="md:col-span-6" />
            @endif
        </x-admin.form-section>
        <x-admin.form-section :title="$user->exists ? 'Set a new password' : 'Password'" :description="$user->exists ? 'Leave empty to keep the current password.' : 'At least 10 characters with letters and numbers. Share it securely.'">
            @if ($user->exists)
                <x-admin.input name="password" label="New password" type="password" autocomplete="new-password" />
            @else
                <x-admin.input name="password" label="Password" type="password" autocomplete="new-password" required />
            @endif
            <x-admin.input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" />
        </x-admin.form-section>
    </form>

    @if ($user->exists)
        <x-admin.card title="Two-factor authentication" class="mt-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm">
                    @if ($user->hasTwoFactorEnabled())
                        <x-admin.status-chip status="enabled" label="Enabled" /> since {{ $user->two_factor_confirmed_at?->format('j M Y') }}
                    @else
                        <x-admin.status-chip status="disabled" label="Not set up" />
                    @endif
                </p>
                @if ($user->hasTwoFactorEnabled() && ! $isSelf)
                    <form method="POST" action="{{ route('admin.users.two-factor.reset', $user) }}" data-confirm="Reset two-factor authentication of {{ $user->name }}? They will sign in with the password only (and enrol again if 2FA is required)." data-confirm-ok="Reset 2FA">
                        @csrf
                        <button type="submit" class="btn-danger btn-sm">Reset 2FA</button>
                    </form>
                @elseif ($isSelf)
                    <a href="{{ route('admin.two-factor.show') }}" class="btn-link">Manage your 2FA</a>
                @endif
            </div>
        </x-admin.card>
    @endif
</x-admin.layout>
