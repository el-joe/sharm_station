<?php

namespace App\Repositories\Eloquent;

use App\Enums\PublishStatus;
use App\Models\BlogPost;
use App\Repositories\Contracts\BlogPostRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<BlogPost>
 */
class BlogPostRepository extends BaseRepository implements BlogPostRepositoryInterface
{
    protected function model(): string
    {
        return BlogPost::class;
    }

    public function adminQuery(?PublishStatus $status = null): Builder
    {
        return $this->query()->select('blog_posts.*')->when($status, fn (Builder $query, PublishStatus $status) => $query->where('status', $status));
    }

    public function paginatePublished(int $perPage, ?string $tag = null): LengthAwarePaginator
    {
        return $this->query()
            ->published()
            ->when($tag, fn (Builder $query, string $tag) => $query->where(fn (Builder $query) => $query
                ->whereJsonContains('tags->en', $tag)->orWhereJsonContains('tags->it', $tag)))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findPublishedBySlug(string $slug): ?BlogPost
    {
        return $this->query()->published()->where('slug', $slug)->first();
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        return $this->query()->where('slug', $slug)->when($exceptId, fn (Builder $query, int $id) => $query->whereKeyNot($id))->exists();
    }
}
