<?php

use App\Enums\PublishStatus;
use App\Enums\TripType;
use App\Models\BlogPost;
use App\Models\Trip;

test('changing a slug keeps the old one as a 301 redirect to the current slug, without chains or loops', function () {
    $trip = Trip::factory()->create(['slug' => 'nile-cruise', 'type' => TripType::Nile]);

    $trip->update(['slug' => 'nile-cruise-5-days']);
    $trip->update(['slug' => 'luxor-aswan-cruise']);

    $this->getJson('/api/v1/redirects/resolve?path=/it/nile/nile-cruise')
        ->assertOk()
        ->assertJsonPath('data', ['type' => 'trip', 'old_slug' => 'nile-cruise', 'slug' => 'luxor-aswan-cruise', 'path' => '/it/nile/luxor-aswan-cruise', 'status' => 301]);
    $this->getJson('/api/v1/redirects/resolve?type=trip&slug=nile-cruise-5-days')->assertOk()->assertJsonPath('data.path', '/en/nile/luxor-aswan-cruise');

    $trip->update(['slug' => 'nile-cruise']);
    $this->getJson('/api/v1/redirects/resolve?path=/en/nile/nile-cruise')->assertNotFound();
    $this->getJson('/api/v1/redirects/resolve?path=/en/nile/luxor-aswan-cruise')->assertOk()->assertJsonPath('data.slug', 'nile-cruise');
});

test('detail endpoints answer 404 with redirect_to when the slug moved', function () {
    $trip = Trip::factory()->create(['slug' => 'old-safari']);
    $trip->update(['slug' => 'quad-safari']);

    $this->getJson('/api/v1/trips/old-safari')->assertNotFound()->assertJsonPath('redirect_to.path', '/en/trips/quad-safari');
    $this->getJson('/api/v1/trips/never-existed')->assertNotFound()->assertJsonMissingPath('redirect_to');
});

test('unpublished records and unknown paths do not redirect', function () {
    $post = BlogPost::factory()->create(['slug' => 'old-post', 'status' => PublishStatus::Published]);
    $post->update(['slug' => 'new-post']);
    $this->getJson('/api/v1/redirects/resolve?path=/en/blog/old-post')->assertOk()->assertJsonPath('data.path', '/en/blog/new-post');

    $post->update(['status' => PublishStatus::Draft]);
    $this->getJson('/api/v1/redirects/resolve?path=/en/blog/old-post')->assertNotFound();
    $this->getJson('/api/v1/redirects/resolve?path=/en/contact')->assertNotFound();
    $this->getJson('/api/v1/redirects/resolve')->assertUnprocessable();
});
