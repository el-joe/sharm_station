<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\TwoFactorController;
use App\Models\Setting;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

function enrolledAdmin(array $codes = ['AAAAA-BBBBB']): User
{
    $admin = staffUser();
    $admin->forceFill([
        'two_factor_secret' => app(Google2FA::class)->generateSecretKey(32),
        'two_factor_recovery_codes' => array_map(fn (string $code): string => Hash::make($code), $codes),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $admin;
}

function currentCode(User $user): string
{
    return app(Google2FA::class)->getCurrentOtp($user->fresh()->two_factor_secret);
}

test('a super admin enrols with the QR code, gets one-time recovery codes once', function () {
    $admin = staffUser();

    $this->actingAs($admin)->get(route('admin.two-factor.show'))->assertOk()->assertSee('<svg', false);
    $secret = session(TwoFactorController::SETUP_SESSION_KEY);

    $this->actingAs($admin)->post(route('admin.two-factor.enable'), ['code' => '000000'])->assertSessionHasErrors('code');
    $this->actingAs($admin)->post(route('admin.two-factor.enable'), ['code' => app(Google2FA::class)->getCurrentOtp($secret)])
        ->assertRedirect(route('admin.two-factor.show'))
        ->assertSessionHas('two_factor_recovery_codes', fn (array $codes) => count($codes) === TwoFactorService::RECOVERY_CODES);

    $admin->refresh();
    expect($admin->hasTwoFactorEnabled())->toBeTrue()
        ->and($admin->two_factor_secret)->toBe($secret)
        ->and(Hash::isHashed($admin->two_factor_recovery_codes[0]))->toBeTrue();
    $this->assertDatabaseMissing('users', ['id' => $admin->id, 'two_factor_secret' => $secret]);
});

test('sign-in with 2FA asks for the code, accepts each code once and recovery codes once', function () {
    $admin = enrolledAdmin();

    $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.two-factor.challenge'));
    $this->assertGuest();
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));

    $this->post(route('admin.two-factor.challenge.store'), ['code' => '123456'])->assertSessionHasErrors('code');
    $code = currentCode($admin);
    $this->post(route('admin.two-factor.challenge.store'), ['code' => $code])->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($admin);

    $this->post(route('admin.logout'));
    $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password']);
    $this->post(route('admin.two-factor.challenge.store'), ['code' => $code])->assertSessionHasErrors('code');
    $this->post(route('admin.two-factor.challenge.store'), ['recovery_code' => 'aaaaa-bbbbb'])->assertRedirect(route('admin.dashboard'));
    expect($admin->fresh()->two_factor_recovery_codes)->toBe([]);

    $this->post(route('admin.logout'));
    $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password']);
    $this->post(route('admin.two-factor.challenge.store'), ['recovery_code' => 'AAAAA-BBBBB'])->assertSessionHasErrors('recovery_code');
    $this->assertGuest();
});

test('the challenge cannot be opened without the password step', function () {
    $this->get(route('admin.two-factor.challenge'))->assertRedirect(route('admin.login'));
    $this->post(route('admin.two-factor.challenge.store'), ['code' => '123456'])->assertRedirect(route('admin.login'));
});

test('when required, super admins without 2FA must enrol before using the back office', function () {
    Setting::factory()->create(['key' => 'security.require_two_factor', 'value' => true]);
    $admin = staffUser();

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('admin.two-factor.show'));
    $this->actingAs($admin)->get(route('admin.two-factor.show'))->assertOk();
    $this->actingAs(staffUser(UserRole::Agent))->get(route('admin.dashboard'))->assertOk();

    $enrolled = enrolledAdmin();
    $this->actingAs($enrolled)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($enrolled)->delete(route('admin.two-factor.disable'), ['code' => currentCode($enrolled)])->assertForbidden();
});

test('another super admin resets 2FA, nobody resets their own through that action', function () {
    $enrolled = enrolledAdmin();
    $other = staffUser();

    $this->actingAs($enrolled)->post(route('admin.users.two-factor.reset', $enrolled))->assertForbidden();
    $this->actingAs($other)->post(route('admin.users.two-factor.reset', $enrolled))->assertRedirect();

    expect($enrolled->fresh()->hasTwoFactorEnabled())->toBeFalse();
    $this->assertDatabaseHas('activity_logs', ['action' => 'two_factor_disabled', 'subject_id' => $enrolled->id, 'causer_id' => $other->id]);
    $this->actingAs(staffUser(UserRole::Agent))->post(route('admin.users.two-factor.reset', $other))->assertForbidden();
});

test('the user disables their own 2FA with a valid code when it is not required', function () {
    $admin = enrolledAdmin();

    $this->actingAs($admin)->delete(route('admin.two-factor.disable'), ['code' => '000000'])->assertSessionHasErrors('code');
    $this->actingAs($admin)->delete(route('admin.two-factor.disable'), ['code' => currentCode($admin)])->assertRedirect(route('admin.two-factor.show'));

    expect($admin->fresh()->hasTwoFactorEnabled())->toBeFalse();
});
