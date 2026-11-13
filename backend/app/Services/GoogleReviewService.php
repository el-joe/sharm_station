<?php

namespace App\Services;

use App\Exceptions\GoogleReviewSyncException;
use App\Models\GoogleReview;
use App\Repositories\Contracts\GoogleReviewRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Google reviews (CR-4): read from the Google Places API (New) on the server, cached in google_reviews.
 * The place-level rating and review count are stored in settings "google_reviews.place.{placeId}".
 */
class GoogleReviewService
{
    /**
     * @var array<string, array{rating: float|null, total: int, write_review_url: string}>
     */
    private array $ratings = [];

    public function __construct(
        private readonly GoogleReviewRepositoryInterface $reviews,
        private readonly SettingRepositoryInterface $settings,
    ) {}

    public function defaultPlaceId(): ?string
    {
        return $this->settings->get('google_reviews.place_id') ?? config('services.google.place_id');
    }

    /**
     * Fetches the place details and upserts its reviews. Hidden flags set by the admin are preserved.
     *
     * @return int Number of reviews received from Google.
     *
     * @throws GoogleReviewSyncException
     */
    public function sync(?string $placeId = null): int
    {
        $placeId ??= $this->defaultPlaceId();
        $apiKey = config('services.google.places_key');

        if (blank($placeId)) {
            throw new GoogleReviewSyncException('No Google Place ID is configured.');
        }

        if (blank($apiKey)) {
            throw new GoogleReviewSyncException('The Google Places API key is not configured.');
        }

        try {
            $response = Http::withHeaders([
                'X-Goog-Api-Key' => $apiKey,
                'X-Goog-FieldMask' => 'id,rating,userRatingCount,reviews,googleMapsUri',
            ])
                ->acceptJson()
                ->timeout(10)
                ->retry(2, 200, throw: false)
                ->get(rtrim((string) config('services.google.places_endpoint'), '/').'/'.urlencode($placeId));
        } catch (ConnectionException $exception) {
            throw new GoogleReviewSyncException('Could not reach the Google Places API.', previous: $exception);
        }

        if ($response->failed()) {
            throw new GoogleReviewSyncException('Google Places API error ('.$response->status().'): '.($response->json('error.message') ?? 'unknown error'));
        }

        $syncedAt = now();
        $rows = [];

        foreach ($response->json('reviews', []) as $review) {
            $text = $review['originalText'] ?? $review['text'] ?? [];

            $rows[] = [
                'place_id' => $placeId,
                'google_review_id' => $review['name'] ?? sha1(($review['authorAttribution']['uri'] ?? '').($review['publishTime'] ?? '')),
                'author_name' => $review['authorAttribution']['displayName'] ?? 'Google user',
                'author_photo_url' => $review['authorAttribution']['photoUri'] ?? null,
                'author_url' => $review['authorAttribution']['uri'] ?? null,
                'rating' => (int) ($review['rating'] ?? 0),
                'text' => $text['text'] ?? null,
                'language' => $text['languageCode'] ?? null,
                'published_at' => isset($review['publishTime']) ? CarbonImmutable::parse($review['publishTime'])->utc()->toDateTimeString() : null,
                'synced_at' => $syncedAt,
                'is_hidden' => false,
            ];
        }

        $this->reviews->upsertMany($rows);

        $this->settings->set("google_reviews.place.{$placeId}", [
            'rating' => $response->json('rating'),
            'total' => $response->json('userRatingCount'),
            'maps_url' => $response->json('googleMapsUri'),
            'synced_at' => $syncedAt->toIso8601String(),
        ]);

        return count($rows);
    }

    /**
     * Overall rating, total count and the latest visible reviews.
     *
     * @return array{place_id: string|null, rating: float|null, total: int, write_review_url: string|null, maps_url: string|null, synced_at: string|null, reviews: Collection<int, GoogleReview>}
     */
    public function summary(?string $placeId = null, int $limit = 10): array
    {
        $placeId ??= $this->defaultPlaceId();

        if (blank($placeId)) {
            return ['place_id' => null, 'rating' => null, 'total' => 0, 'write_review_url' => null, 'maps_url' => null, 'synced_at' => null, 'reviews' => new Collection];
        }

        $place = $this->settings->get("google_reviews.place.{$placeId}", []);
        $stats = $this->reviews->statsFor($placeId);

        return [
            'place_id' => $placeId,
            'rating' => isset($place['rating']) ? round((float) $place['rating'], 1) : $stats['average'],
            'total' => (int) ($place['total'] ?? $stats['count']),
            'write_review_url' => $this->writeReviewUrl($placeId),
            'maps_url' => $place['maps_url'] ?? null,
            'synced_at' => $place['synced_at'] ?? null,
            'reviews' => $this->reviews->visibleFor($placeId, $limit),
        ];
    }

    /**
     * Cached place-level rating only (no review rows), for cards and listings.
     *
     * @return array{rating: float|null, total: int, write_review_url: string}|null
     */
    public function rating(?string $placeId): ?array
    {
        if (blank($placeId)) {
            return null;
        }

        return $this->ratings[$placeId] ??= (function () use ($placeId): array {
            $place = $this->settings->get("google_reviews.place.{$placeId}", []);

            return [
                'rating' => isset($place['rating']) ? round((float) $place['rating'], 1) : null,
                'total' => (int) ($place['total'] ?? 0),
                'write_review_url' => $this->writeReviewUrl($placeId),
            ];
        })();
    }

    public function writeReviewUrl(string $placeId): string
    {
        return 'https://search.google.com/local/writereview?placeid='.urlencode($placeId);
    }

    public function hide(GoogleReview $review): GoogleReview
    {
        return $this->reviews->update($review, ['is_hidden' => true]);
    }

    public function show(GoogleReview $review): GoogleReview
    {
        return $this->reviews->update($review, ['is_hidden' => false]);
    }

    /**
     * Every cached review of a place, hidden ones included (admin).
     *
     * @return Collection<int, GoogleReview>
     */
    public function allFor(string $placeId): Collection
    {
        return $this->reviews->allFor($placeId);
    }

    public function setDefaultPlaceId(?string $placeId): void
    {
        $this->settings->set('google_reviews.place_id', filled($placeId) ? trim($placeId) : null);
    }

    public function setHidden(GoogleReview $review, bool $hidden): GoogleReview
    {
        return $hidden ? $this->hide($review) : $this->show($review);
    }
}
