<?php

namespace App\Repositories\Eloquent;

use App\Models\SlugRedirect;
use App\Repositories\Contracts\SlugRedirectRepositoryInterface;

/**
 * @extends BaseRepository<SlugRedirect>
 */
class SlugRedirectRepository extends BaseRepository implements SlugRedirectRepositoryInterface
{
    protected function model(): string
    {
        return SlugRedirect::class;
    }

    public function findByOldSlug(string $subjectType, string $slug): ?SlugRedirect
    {
        return $this->query()->where('subject_type', $subjectType)->where('old_slug', $slug)->first();
    }

    public function record(string $subjectType, int $subjectId, string $oldSlug, string $newSlug): void
    {
        $this->query()->where('subject_type', $subjectType)->where('old_slug', $newSlug)->delete();
        $this->query()->updateOrCreate(['subject_type' => $subjectType, 'old_slug' => $oldSlug], ['subject_id' => $subjectId]);
    }
}
