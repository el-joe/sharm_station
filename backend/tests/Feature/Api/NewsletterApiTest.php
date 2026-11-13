<?php

use App\Models\NewsletterSubscriber;

test('a visitor subscribes to the newsletter once, repeated sign-ups are idempotent', function () {
    $this->postJson('/api/v1/newsletter', ['email' => 'Giulia@Example.com', 'locale' => 'it'])
        ->assertCreated()
        ->assertJsonPath('data.email', 'giulia@example.com')
        ->assertJsonPath('data.subscribed', true)
        ->assertJsonPath('data.already_subscribed', false);

    $this->postJson('/api/v1/newsletter', ['email' => 'giulia@example.com', 'locale' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.already_subscribed', true)
        ->assertJsonPath('data.locale', 'en');

    expect(NewsletterSubscriber::query()->count())->toBe(1);
});

test('an unsubscribed address is re-activated', function () {
    NewsletterSubscriber::query()->create(['email' => 'paolo@example.com', 'locale' => 'en', 'subscribed_at' => now()->subYear(), 'unsubscribed_at' => now()->subMonth()]);

    $this->postJson('/api/v1/newsletter', ['email' => 'paolo@example.com'])->assertOk();

    expect(NewsletterSubscriber::query()->sole()->unsubscribed_at)->toBeNull();
});

test('the newsletter validates the email and locale', function () {
    $this->postJson('/api/v1/newsletter', ['email' => 'not-an-email', 'locale' => 'fr'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'locale']);
});
