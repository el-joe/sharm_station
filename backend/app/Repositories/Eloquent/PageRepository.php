<?php

namespace App\Repositories\Eloquent;

use App\Models\Page;
use App\Repositories\Contracts\PageRepositoryInterface;

/**
 * @extends BaseRepository<Page>
 */
class PageRepository extends BaseRepository implements PageRepositoryInterface
{
    protected function model(): string
    {
        return Page::class;
    }

    public function findBySlug(string $slug): ?Page
    {
        return $this->query()->where('slug', $slug)->first();
    }
}
