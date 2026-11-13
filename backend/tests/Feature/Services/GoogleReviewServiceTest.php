<?php

use App\Exceptions\GoogleReviewSyncException;
use App\Models\GoogleReview;
use App\Services\GoogleReviewService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.google.places_key' => 'test-key', 'services.google.place_id' => 'ChIJ-test-place']);
    $this->service = app(GoogleReviewService::class);
});

function placesResponse(array $reviews, float $rating = 4.7, int $total = 321): array
{
    return [
        'id' => 'ChIJ-test-place',
        'rating' => $rating,
        'userRatingCount' => $total,
        'googleMapsUri' => 'https://maps.google.com/?cid=123',
        'reviews' => $reviews,
    ];
}

function placesReview(string $id, string $text, int $rating = 5): array
{
    return [
        'name' => "places/ChIJ-test-place/reviews/{$id}",
        'rating' => $rating,
        'text' => ['text' => $text, 'languageCode' => 'en'],
        'originalText' => ['text' => $text, 'languageCode' => 'en'],
        'authorAttribution' => ['displayName' => 'Jane Doe', 'uri' => 'https://www.google.com/maps/contrib/1', 'photoUri' => 'https://lh3.googleusercontent.com/a/photo'],
        'publishTime' => '2026-10-01T10:00:00Z',
    ];
}

test('sync stores the reviews and the place rating from the Places API', function () {
    Http::fake(['places.googleapis.com/*' => Http::response(placesResponse([placesReview('r1', 'Great trip'), placesReview('r2', 'Good', 4)]))]);

    expect($this->service->sync())->toBe(2);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Goog-Api-Key', 'test-key')
        && str_ends_with($request->url(), '/places/ChIJ-test-place'));

    $summary = $this->service->summary();

    expect(GoogleReview::query()->count())->toBe(2)
        ->and($summary['rating'])->toBe(4.7)
        ->and($summary['total'])->toBe(321)
        ->and($summary['write_review_url'])->toBe('https://search.google.com/local/writereview?placeid=ChIJ-test-place')
        ->and($summary['reviews']->first()->author_name)->toBe('Jane Doe');
});

test('a second sync updates reviews and keeps hidden ones hidden', function () {
    Http::fakeSequence('places.googleapis.com/*')
        ->push(placesResponse([placesReview('r1', 'First text')]))
        ->push(placesResponse([placesReview('r1', 'Edited text')]));

    $this->service->sync();
    $this->service->hide(GoogleReview::query()->first());
    $this->service->sync();

    $review = GoogleReview::query()->sole();

    expect($review->text)->toBe('Edited text')
        ->and($review->is_hidden)->toBeTrue()
        ->and($this->service->summary()['reviews'])->toHaveCount(0);
});

test('hidden reviews can be shown again', function () {
    $review = GoogleReview::factory()->hidden()->create();

    $this->service->show($review);

    expect($review->fresh()->is_hidden)->toBeFalse();
});

test('sync fails clearly without an API key or when Google returns an error', function () {
    Http::fake(['places.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid']], 400)]);

    expect(fn () => $this->service->sync())->toThrow(GoogleReviewSyncException::class, 'Google Places API error (400): API key not valid');

    config(['services.google.places_key' => null]);

    expect(fn () => $this->service->sync())->toThrow(GoogleReviewSyncException::class, 'The Google Places API key is not configured.');
});

test('the summary falls back to the cached reviews average before the first sync', function () {
    GoogleReview::factory()->create(['rating' => 5]);
    GoogleReview::factory()->create(['rating' => 4]);
    GoogleReview::factory()->hidden()->create(['rating' => 1]);

    $summary = $this->service->summary();

    expect($summary['rating'])->toBe(4.5)
        ->and($summary['total'])->toBe(2);
});
