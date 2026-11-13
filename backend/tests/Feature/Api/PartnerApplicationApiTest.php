<?php

use App\Enums\PartnerStatus;
use App\Models\Partner;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('a visitor can apply as a partner and gets a pending account', function () {
    $this->postJson('/api/v1/partners/apply', [
        'company' => 'Alpha Travel Srl',
        'name' => 'Marco Bianchi',
        'email' => 'marco@alphatravel.example',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
        'country' => 'it',
        'website' => 'https://alphatravel.example',
    ])
        ->assertCreated()
        ->assertJsonPath('data.company', 'Alpha Travel Srl')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonStructure(['token']);

    $partner = Partner::query()->sole();

    expect($partner->status)->toBe(PartnerStatus::Pending)
        ->and($partner->country)->toBe('IT')
        ->and($partner->user->email)->toBe('marco@alphatravel.example')
        ->and($partner->user->activePartner())->toBeNull();
});

test('a signed in customer can apply without creating a new account', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/partners/apply', ['company' => 'Beta Viaggi'])
        ->assertCreated()
        ->assertJsonPath('token', null);

    expect(Partner::query()->sole()->user_id)->toBe($user->id);

    $this->postJson('/api/v1/partners/apply', ['company' => 'Beta Viaggi'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.company.0', 'You have already applied as a partner.');
});

test('a visitor must provide account details and cannot reuse an email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/partners/apply', ['company' => 'Gamma'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password']);

    $this->postJson('/api/v1/partners/apply', [
        'company' => 'Gamma',
        'name' => 'Gamma Owner',
        'email' => 'taken@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
});
