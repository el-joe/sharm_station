<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\BlogPostResource;
use App\Http\Resources\V1\PageResource;
use App\Models\FaqCategory;
use App\Models\FaqItem;
use App\Services\ContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Website content edited in the admin: static pages, blog and FAQ (W2).
 */
class ContentController extends ApiController
{
    public function __construct(private readonly ContentService $content) {}

    public function page(string $slug): PageResource
    {
        $page = $this->content->findPage($slug);
        abort_if($page === null, 404, 'Page not found.');

        return new PageResource($page);
    }

    public function blog(Request $request): AnonymousResourceCollection
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 12)));
        $tag = is_string($request->query('tag')) ? $request->query('tag') : null;

        return BlogPostResource::collection($this->content->publishedPosts($perPage, $tag));
    }

    public function post(string $slug): BlogPostResource
    {
        $post = $this->content->publishedPost($slug);
        abort_if($post === null, 404, 'Post not found.');

        return new BlogPostResource($post);
    }

    public function faqs(): JsonResponse
    {
        return response()->json(['data' => $this->content->faqCategories(activeOnly: true)
            ->filter(fn (FaqCategory $category): bool => $category->items->isNotEmpty())
            ->map(fn (FaqCategory $category): array => [
                'id' => $category->key,
                'category' => $category->title,
                'items' => $category->items->map(fn (FaqItem $item): array => [
                    'question' => $item->question,
                    'answer_html' => $item->answer ?? '',
                ])->values()->all(),
            ])->values()->all()]);
    }
}
