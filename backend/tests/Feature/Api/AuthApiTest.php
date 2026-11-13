<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('a customer can register and receives a token', function () {
    seedCurrencies();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Giulia Rossi',
        'email' => 'Giulia@Example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
        'locale' => 'it',
        'currency' => 'USD',
    ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'giulia@example.com')
        ->assertJsonPath('data.role', 'customer')
        ->assertJsonPath('data.locale', 'it')
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonStructure(['token']);
});

test('registration validates the payload and unique email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/auth/register', ['name' => '', 'email' => 'taken@example.com', 'password' => 'short'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

test('a user can log in with valid credentials', function () {
    User::factory()->create(['email' => 'giulia@example.com', 'password' => 'secret-password']);

    $this->postJson('/api/v1/auth/login', ['email' => 'giulia@example.com', 'password' => 'secret-password'])
        ->assertOk()
        ->assertJsonPath('data.email', 'giulia@example.com')
        ->assertJsonStructure(['token']);
});

test('invalid credentials are rejected', function () {
    User::factory()->create(['email' => 'giulia@example.com', 'password' => 'secret-password']);

    $this->postJson('/api/v1/auth/login', ['email' => 'giulia@example.com', 'password' => 'wrong'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
});

test('me requires a token and returns the user', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create(['name' => 'Giulia']));

    $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.name', 'Giulia');
});

test('logout revokes the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('web')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});
