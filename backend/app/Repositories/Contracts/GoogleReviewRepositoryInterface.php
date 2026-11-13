<?php

namespace App\Repositories\Contracts;

use App\Models\GoogleReview;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<GoogleReview>
 */
interface GoogleReviewRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Inserts new reviews and refreshes existing ones without touching is_hidden.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function upsertMany(array $rows): int;

    /**
     * @return Collection<int, GoogleReview>
     */
    public function visibleFor(string $placeId, int $limit = 10): Collection;

    /**
     * @return array{average: float|null, count: int}
     */
    public function statsFor(string $placeId): array;

    /**
     * Every cached review of a place (hidden ones included), newest first.
     *
     * @return Collection<int, GoogleReview>
     */
    public function allFor(string $placeId): Collection;
}
