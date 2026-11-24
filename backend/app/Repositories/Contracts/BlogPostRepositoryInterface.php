<?php

namespace App\Repositories\Contracts;

use App\Enums\PublishStatus;
use App\Models\BlogPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepositoryInterface<BlogPost>
 */
interface BlogPostRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return Builder<BlogPost>
     */
    public function adminQuery(?PublishStatus $status = null): Builder;

    public function paginatePublished(int $perPage, ?string $tag = null): LengthAwarePaginator;

    public function findPublishedBySlug(string $slug): ?BlogPost;

    public function slugExists(string $slug, ?int $exceptId = null): bool;
}
