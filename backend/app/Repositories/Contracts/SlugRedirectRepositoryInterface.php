<?php

namespace App\Repositories\Contracts;

use App\Models\SlugRedirect;

/**
 * @extends BaseRepositoryInterface<SlugRedirect>
 */
interface SlugRedirectRepositoryInterface extends BaseRepositoryInterface
{
    public function findByOldSlug(string $subjectType, string $slug): ?SlugRedirect;

    /**
     * Stores $oldSlug as a former slug of the subject and drops any redirect from its new slug (no loops).
     */
    public function record(string $subjectType, int $subjectId, string $oldSlug, string $newSlug): void;
}
