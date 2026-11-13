<?php

use App\Models\Destination;
use App\Models\GoogleReview;
use App\Models\Package;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Trip;

beforeEach(function () {
    seedCurrencies();
    config(['services.google.place_id' => 'ChIJ-test-place']);
});

test('meta returns locales, currencies, destinations and public settings', function () {
    Destination::factory()->create(['slug' => 'luxor', 'name' => ['en' => 'Luxor', 'it' => 'Luxor']]);
    Setting::factory()->create(['key' => 'site.name', 'value' => 'Sharm Station Viaggi']);
    Setting::factory()->create(['key' => 'payment.secret', 'value' => 'hidden']);

    $this->getJson('/api/v1/meta?currency=USD&locale=it')
        ->assertOk()
        ->assertJsonPath('data.locale', 'it')
        ->assertJsonPath('data.locales', ['en', 'it'])
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonCount(3, 'data.currencies')
        ->assertJsonPath('data.destinations.0.slug', 'luxor')
        ->assertJsonPath('data.settings', ['site.name' => 'Sharm Station Viaggi']);
});

test('home returns the featured content blocks and the Google rating', function () {
    Trip::factory()->create(['featured' => true]);
    Trip::factory()->nile()->create();
    Trip::factory()->draft()->create();
    Package::factory()->create();
    Room::factory()->create();
    GoogleReview::factory()->create(['place_id' => 'ChIJ-test-place', 'rating' => 5]);
    Setting::factory()->create(['key' => 'google_reviews.place.ChIJ-test-place', 'value' => ['rating' => 4.8, 'total' => 1264]]);

    $this->getJson('/api/v1/home')
        ->assertOk()
        ->assertJsonStructure(['data' => ['stats', 'featured_trips', 'nile_trips', 'packages', 'rooms', 'destinations', 'google_reviews' => ['rating', 'total', 'write_review_url', 'reviews']]])
        ->assertJsonPath('data.stats.trips', 2)
        ->assertJsonCount(1, 'data.featured_trips')
        ->assertJsonCount(1, 'data.nile_trips')
        ->assertJsonCount(1, 'data.packages')
        ->assertJsonCount(1, 'data.rooms')
        ->assertJsonPath('data.google_reviews.rating', 4.8)
        ->assertJsonPath('data.google_reviews.total', 1264);
});
