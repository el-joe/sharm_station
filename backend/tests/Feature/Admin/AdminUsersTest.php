<?php

use App\Enums\UserRole;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = staffUser();
});

test('super admins create staff accounts that can sign in', function () {
    $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk()->assertSee('Permissions by role')->assertSee('Users and roles, activity log');

    $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'Nour Ahmed', 'email' => 'Nour@SharmStation.test', 'role' => 'editor', 'is_active' => '1',
        'password' => 'Pyramids2026', 'password_confirmation' => 'Pyramids2026',
    ])->assertRedirect(route('admin.users.index'));

    $user = User::query()->where('email', 'nour@sharmstation.test')->firstOrFail();
    expect($user->role)->toBe(UserRole::Editor)->and($user->isActive())->toBeTrue();
    $this->assertDatabaseHas('activity_logs', ['action' => 'created', 'subject_type' => 'user', 'subject_id' => $user->id]);

    auth()->logout();
    $this->post(route('admin.login.store'), ['email' => 'nour@sharmstation.test', 'password' => 'Pyramids2026'])->assertRedirect(route('admin.dashboard'));

    $row = $this->actingAs($this->admin)->getJson(route('admin.users.data', ['draw' => 1, 'start' => 0, 'length' => 10, 'role' => 'editor']))->json();
    expect($row['recordsFiltered'])->toBe(1);
});

test('passwords must be confirmed and strong; customers are not staff', function () {
    $this->actingAs($this->admin)->post(route('admin.users.store'), ['name' => 'A', 'email' => 'a@example.com', 'role' => 'agent', 'password' => 'short', 'password_confirmation' => 'other'])
        ->assertSessionHasErrors('password');
    $this->actingAs($this->admin)->post(route('admin.users.store'), ['name' => 'A', 'email' => 'a@example.com', 'role' => 'customer', 'password' => 'Pyramids2026', 'password_confirmation' => 'Pyramids2026'])
        ->assertSessionHasErrors('role');
    $this->actingAs($this->admin)->get(route('admin.users.edit', User::factory()->create()))->assertNotFound();
});

test('nobody can demote or deactivate themselves, and the last super admin is protected', function () {
    $agent = staffUser(UserRole::Agent);

    $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), ['name' => 'Me', 'email' => $this->admin->email, 'role' => 'agent', 'is_active' => '1'])
        ->assertSessionHasErrors(['role' => 'You cannot change your own role or deactivate yourself.']);
    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin))->assertSessionHasErrors('user');

    $other = staffUser(UserRole::SuperAdmin);
    $this->actingAs($other)->put(route('admin.users.update', $this->admin), ['name' => 'Boss', 'email' => $this->admin->email, 'role' => 'super_admin', 'is_active' => '0'])
        ->assertRedirect();
    expect($this->admin->fresh()->isActive())->toBeFalse();

    $this->actingAs($agent->fresh())->get(route('admin.users.index'))->assertForbidden();
    $agent->update(['role' => UserRole::SuperAdmin, 'is_active' => false]);
    $this->actingAs($other)->put(route('admin.users.update', $other), ['name' => 'Last', 'email' => $other->email, 'role' => 'super_admin', 'is_active' => '1'])->assertRedirect();
    $this->actingAs($other)->delete(route('admin.users.destroy', $other))->assertSessionHasErrors('user');

    $this->actingAs($other)->put(route('admin.users.update', $this->admin), ['name' => 'Boss', 'email' => $this->admin->email, 'role' => 'agent', 'is_active' => '1', 'password' => 'NewPassword1', 'password_confirmation' => 'NewPassword1'])
        ->assertRedirect();
    expect($this->admin->fresh()->role)->toBe(UserRole::Agent);
    $this->assertDatabaseHas('activity_logs', ['action' => 'password_changed', 'subject_id' => $this->admin->id]);
});

test('deactivated staff cannot sign in and are signed out', function () {
    $agent = staffUser(UserRole::Agent);
    $agent->update(['is_active' => false]);

    $this->post(route('admin.login.store'), ['email' => $agent->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->actingAs($agent)->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});
