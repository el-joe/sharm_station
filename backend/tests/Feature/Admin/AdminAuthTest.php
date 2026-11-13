<?php

use App\Enums\UserRole;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

test('guests are redirected to the admin login and /admin goes to the dashboard', function () {
    $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));
    $this->get(route('admin.login'))->assertOk()->assertSee('Sign in');

    $this->actingAs(staffUser())->get('/admin')->assertRedirect('/admin/dashboard');
});

test('staff can sign in with remember me and sign out', function () {
    $user = staffUser(UserRole::Agent);

    $this->post(route('admin.login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => '1'])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->remember_token)->not->toBeNull();

    $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
    $this->assertGuest();
});

test('wrong credentials and non-staff accounts are rejected', function () {
    $customer = User::factory()->create();
    $agent = staffUser(UserRole::Agent);

    $this->from(route('admin.login'))->post(route('admin.login.store'), ['email' => $agent->email, 'password' => 'wrong'])
        ->assertRedirect(route('admin.login'))
        ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

    $this->from(route('admin.login'))->post(route('admin.login.store'), ['email' => $customer->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'This account has no access to the back office.']);

    $this->assertGuest();
});

test('login attempts are throttled', function () {
    $agent = staffUser(UserRole::Agent);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('admin.login.store'), ['email' => $agent->email, 'password' => 'wrong']);
    }

    $this->post(route('admin.login.store'), ['email' => $agent->email, 'password' => 'wrong'])->assertTooManyRequests();
});

test('customers and partners signed in on the web guard are kicked out of the admin', function (UserRole $role) {
    $this->actingAs(User::factory()->role($role)->create())
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.login'));

    $this->assertGuest();
})->with([UserRole::Customer, UserRole::Partner]);

test('editors manage content but cannot open sales, finance or settings', function () {
    $editor = staffUser(UserRole::Editor);

    $this->actingAs($editor)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Revenue this month');
    $this->actingAs($editor)->get(route('admin.trips.index'))->assertOk();
    $this->actingAs($editor)->get(route('admin.bookings.index'))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.bookings.data'))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.partners.commissions.edit'))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.currencies.index'))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.settings.edit'))->assertForbidden();
});

test('agents reach sales but only super admins reach settings', function () {
    $agent = staffUser(UserRole::Agent);

    $this->actingAs($agent)->get(route('admin.bookings.index'))->assertOk();
    $this->actingAs($agent)->get(route('admin.settings.edit'))->assertForbidden();
    $this->actingAs(staffUser())->get(route('admin.settings.edit'))->assertOk();
});
