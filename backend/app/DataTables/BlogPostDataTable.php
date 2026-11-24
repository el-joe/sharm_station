<?php

namespace App\DataTables;

use App\DataTables\Concerns\RendersRowActions;
use App\Enums\PublishStatus;
use App\Models\BlogPost;
use App\Services\ContentService;
use App\View\Components\Admin\StatusChip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

class BlogPostDataTable
{
    use RendersRowActions;

    public function __construct(private readonly ContentService $content) {}

    public function json(?PublishStatus $status): JsonResponse
    {
        return (new EloquentDataTable($this->content->postsAdminQuery($status)))
            ->addColumn('title', fn (BlogPost $post): string => $this->titleCell((string) $post->translate('title', 'en'), '/blog/'.$post->slug, route('admin.content.posts.edit', $post)))
            ->editColumn('status', fn (BlogPost $post): string => StatusChip::html($post->status))
            ->editColumn('published_at', fn (BlogPost $post): string => e($post->published_at?->format('j M Y') ?? '—'))
            ->addColumn('languages', fn (BlogPost $post): string => e(strtoupper(implode(' · ', array_keys($post->getTranslations('title'))))))
            ->addColumn('actions', fn (BlogPost $post): string => $this->actions(
                $this->editLink(route('admin.content.posts.edit', $post)),
                $this->deleteForm(route('admin.content.posts.destroy', $post), 'Delete the post “'.$post->translate('title', 'en').'”?'),
            ))
            ->filterColumn('title', fn (Builder $query, string $keyword) => $query->where(fn (Builder $query) => $query
                ->where('blog_posts.title->en', 'like', "%{$keyword}%")->orWhere('blog_posts.title->it', 'like', "%{$keyword}%")->orWhere('blog_posts.slug', 'like', "%{$keyword}%")))
            ->orderColumn('title', fn (Builder $query, string $direction) => $query->orderBy('blog_posts.title->en', $direction))
            ->rawColumns(['title', 'status', 'published_at', 'languages', 'actions'])
            ->toJson();
    }
}
