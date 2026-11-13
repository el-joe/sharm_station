<?php

namespace App\Repositories\Eloquent;

use App\Models\GoogleReview;
use App\Repositories\Contracts\GoogleReviewRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<GoogleReview>
 */
class GoogleReviewRepository extends BaseRepository implements GoogleReviewRepositoryInterface
{
    protected function model(): string
    {
        return GoogleReview::class;
    }

    public function upsertMany(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $now = now();
        $rows = array_map(fn (array $row): array => [...$row, 'created_at' => $now, 'updated_at' => $now], $rows);

        $this->query()->upsert(
            $rows,
            ['place_id', 'google_review_id'],
            ['author_name', 'author_photo_url', 'author_url', 'rating', 'text', 'language', 'published_at', 'synced_at', 'updated_at'],
        );

        return count($rows);
    }

    public function visibleFor(string $placeId, int $limit = 10): Collection
    {
        return $this->query()
            ->where('place_id', $placeId)
            ->visible()
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    public function statsFor(string $placeId): array
    {
        $stats = $this->query()
            ->where('place_id', $placeId)
            ->visible()
            ->selectRaw('AVG(rating) as average, COUNT(*) as total')
            ->first();

        return [
            'average' => $stats?->average !== null ? round((float) $stats->average, 1) : null,
            'count' => (int) ($stats?->total ?? 0),
        ];
    }

    public function allFor(string $placeId): Collection
    {
        return $this->query()->where('place_id', $placeId)->orderByDesc('published_at')->get();
    }
}
