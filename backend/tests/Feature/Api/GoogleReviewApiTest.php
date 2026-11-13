<?php

use App\Models\GoogleReview;
use App\Models\Setting;

test('google reviews return the rating, total, visible reviews and the write review link', function () {
    config(['services.google.place_id' => 'ChIJ-test-place']);
    Setting::factory()->create(['key' => 'google_reviews.place.ChIJ-test-place', 'value' => ['rating' => 4.8, 'total' => 1264]]);
    GoogleReview::factory()->count(2)->create(['place_id' => 'ChIJ-test-place']);
    GoogleReview::factory()->hidden()->create(['place_id' => 'ChIJ-test-place', 'author_name' => 'Hidden Author']);

    $response = $this->getJson('/api/v1/reviews/google')
        ->assertOk()
        ->assertJsonPath('data.rating', 4.8)
        ->assertJsonPath('data.total', 1264)
        ->assertJsonPath('data.write_review_url', 'https://search.google.com/local/writereview?placeid=ChIJ-test-place')
        ->assertJsonCount(2, 'data.reviews')
        ->assertJsonStructure(['data' => ['reviews' => [['author_name', 'author_photo_url', 'rating', 'text', 'language', 'published_at', 'relative_time']]]]);

    expect($response->getContent())->not->toContain('Hidden Author');
});

test('the place id stored in settings overrides the config', function () {
    config(['services.google.place_id' => 'ChIJ-config']);
    Setting::factory()->create(['key' => 'google_reviews.place_id', 'value' => 'ChIJ-settings']);
    GoogleReview::factory()->create(['place_id' => 'ChIJ-settings']);

    $this->getJson('/api/v1/reviews/google')
        ->assertJsonPath('data.place_id', 'ChIJ-settings')
        ->assertJsonCount(1, 'data.reviews');
});
