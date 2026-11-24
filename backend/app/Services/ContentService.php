<?php

namespace App\Services;

use App\DTOs\Content\BlogPostData;
use App\DTOs\Content\FaqCategoryData;
use App\DTOs\Content\FaqItemData;
use App\DTOs\Content\PageData;
use App\Enums\PageSlug;
use App\Enums\PublishStatus;
use App\Exceptions\DomainValidationException;
use App\Models\BlogPost;
use App\Models\FaqCategory;
use App\Models\FaqItem;
use App\Models\Page;
use App\Repositories\Contracts\BlogPostRepositoryInterface;
use App\Repositories\Contracts\FaqRepositoryInterface;
use App\Repositories\Contracts\PageRepositoryInterface;
use App\Services\Content\HtmlSanitizer;
use App\Services\Content\WebsiteRevalidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Website content (SRS 14.1 Content): fixed static pages, blog posts and FAQ, edited per language.
 * Every HTML field is sanitized with an allow-list before it is stored, and every change asks the
 * website to revalidate the affected cache tags (after the response, never blocking the save).
 */
class ContentService
{
    public const COVER_DISK = 'public';

    public const COVER_DIRECTORY = 'blog';

    public const WORDS_PER_MINUTE = 200;

    public function __construct(
        private readonly PageRepositoryInterface $pages,
        private readonly BlogPostRepositoryInterface $posts,
        private readonly FaqRepositoryInterface $faqs,
        private readonly HtmlSanitizer $sanitizer,
        private readonly WebsiteRevalidator $website,
    ) {}

    /**
     * Every fixed page, existing or not yet written.
     *
     * @return list<array{slug: PageSlug, page: Page|null}>
     */
    public function pageList(): array
    {
        $existing = $this->pages->all()->keyBy('slug');

        return array_map(fn (PageSlug $slug): array => ['slug' => $slug, 'page' => $existing->get($slug->value)], PageSlug::cases());
    }

    public function pageForEditing(PageSlug $slug): Page
    {
        return $this->pages->findBySlug($slug->value) ?? new Page(['slug' => $slug->value]);
    }

    public function savePage(PageSlug $slug, PageData $data): Page
    {
        $attributes = [
            'title' => $data->title,
            'intro' => $data->intro === [] ? null : $data->intro,
            'body' => $this->sanitizeAll($data->body),
            'seo' => $data->seo === [] ? null : $data->seo,
        ];
        $page = $this->pages->findBySlug($slug->value);

        $page = $page === null
            ? $this->pages->create(['slug' => $slug->value, ...$attributes])
            : $this->pages->update($page, $attributes);

        $this->website->revalidate(WebsiteRevalidator::pageTags($slug->value));

        return $page;
    }

    public function findPage(string $slug): ?Page
    {
        return PageSlug::tryFrom($slug) !== null ? $this->pages->findBySlug($slug) : null;
    }

    /**
     * @return Builder<BlogPost>
     */
    public function postsAdminQuery(?PublishStatus $status = null): Builder
    {
        return $this->posts->adminQuery($status);
    }

    public function createPost(BlogPostData $data): BlogPost
    {
        $post = DB::transaction(fn (): BlogPost => $this->posts->create($this->postAttributes($data, null)));
        $this->website->revalidate(WebsiteRevalidator::postTags($post->slug));

        return $post;
    }

    public function updatePost(BlogPost $post, BlogPostData $data): BlogPost
    {
        $previousSlug = (string) $post->slug;
        $post = DB::transaction(fn (): BlogPost => $this->posts->update($post, $this->postAttributes($data, $post)));
        $this->website->revalidate(WebsiteRevalidator::postTags($previousSlug, $post->slug));

        return $post;
    }

    public function deletePost(BlogPost $post): void
    {
        $slug = (string) $post->slug;
        $this->deleteStoredCover($post->cover_image);
        $this->posts->delete($post);
        $this->website->revalidate(WebsiteRevalidator::postTags($slug));
    }

    public function publishedPosts(int $perPage = 12, ?string $tag = null): LengthAwarePaginator
    {
        return $this->posts->paginatePublished($perPage, $tag);
    }

    public function publishedPost(string $slug): ?BlogPost
    {
        return $this->posts->findPublishedBySlug($slug);
    }

    /**
     * Public URL of a cover: uploaded files live on the public disk, seeded ones are site paths (/images/...).
     */
    public static function coverUrl(?string $cover): ?string
    {
        if ($cover === null || $cover === '') {
            return null;
        }

        return str_starts_with($cover, '/') || str_starts_with($cover, 'http') ? $cover : Storage::disk(self::COVER_DISK)->url($cover);
    }

    /**
     * Reading time from the longest language version, at least one minute.
     *
     * @param  array<string, string>  $bodies
     */
    public function readingMinutes(array $bodies): int
    {
        $words = max([0, ...array_map(fn (string $html): int => str_word_count(strip_tags($html)), $bodies)]);

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
    }

    /**
     * @return Collection<int, FaqCategory>
     */
    public function faqCategories(bool $activeOnly = false): Collection
    {
        return $this->faqs->categoriesWithItems($activeOnly);
    }

    public function createFaqCategory(FaqCategoryData $data): FaqCategory
    {
        $category = $this->faqs->create([
            'key' => $this->uniqueFaqKey($data->key ?: Str::slug($data->title['en'] ?? reset($data->title) ?: 'category')),
            'title' => $data->title,
            'sort' => $this->faqs->nextCategorySort(),
        ]);
        $this->website->revalidate(WebsiteRevalidator::faqTags());

        return $category;
    }

    public function updateFaqCategory(FaqCategory $category, FaqCategoryData $data): FaqCategory
    {
        $category = $this->faqs->update($category, ['title' => $data->title]);
        $this->website->revalidate(WebsiteRevalidator::faqTags());

        return $category;
    }

    public function deleteFaqCategory(FaqCategory $category): void
    {
        $this->faqs->delete($category);
        $this->website->revalidate(WebsiteRevalidator::faqTags());
    }

    public function createFaqItem(FaqItemData $data): FaqItem
    {
        $category = $this->faqs->findOrFail($data->categoryId);

        $item = $this->faqs->createItem($category, [
            'question' => $data->question,
            'answer' => $this->sanitizeAll($data->answer),
            'is_active' => $data->isActive,
            'sort' => $this->faqs->nextItemSort($category),
        ]);
        $this->website->revalidate(WebsiteRevalidator::faqTags());

        return $item;
    }

    public function updateFaqItem(FaqItem $item, FaqItemData $data): FaqItem
    {
        $attributes = ['question' => $data->question, 'answer' => $this->sanitizeAll($data->answer), 'is_active' => $data->isActive];

        if ($data->categoryId !== $item->faq_category_id) {
            $attributes['faq_category_id'] = $this->faqs->findOrFail($data->categoryId)->id;
            $attributes['sort'] = $this->faqs->nextItemSort($this->faqs->findOrFail($data->categoryId));
        }

        $item = $this->faqs->updateItem($item, $attributes);
        $this->website->revalidate(WebsiteRevalidator::faqTags());

        return $item;
    }

    public function deleteFaqItem(FaqItem $item): void
    {
        $this->faqs->deleteItem($item);
        $this->website->revalidate(WebsiteRevalidator::faqTags());
    }

    /**
     * @param  list<int>  $categoryIds
     * @param  array<int, list<int>>  $itemIds  category id => item ids
     */
    public function reorderFaq(array $categoryIds, array $itemIds): void
    {
        DB::transaction(function () use ($categoryIds, $itemIds): void {
            $this->faqs->reorderCategories($categoryIds);

            foreach ($itemIds as $categoryId => $ids) {
                $category = $this->faqs->find((int) $categoryId);

                if ($category !== null) {
                    $this->faqs->reorderItems($category, $ids);
                }
            }
        });
        $this->website->revalidate(WebsiteRevalidator::faqTags());
    }

    /**
     * @param  array<string, string>  $html
     * @return array<string, string>|null
     */
    public function sanitizeAll(array $html): ?array
    {
        $clean = array_filter(array_map(fn (string $value): string => $this->sanitizer->clean($value), $html), fn (string $value): bool => $value !== '');

        return $clean === [] ? null : $clean;
    }

    /**
     * @return array<string, mixed>
     */
    private function postAttributes(BlogPostData $data, ?BlogPost $post): array
    {
        $slug = Str::slug($data->slug ?? ($data->title['en'] ?? reset($data->title) ?: 'post'));

        if ($this->posts->slugExists($slug, $post?->id)) {
            throw DomainValidationException::withErrors(['slug' => ['Another post already uses this address.']]);
        }

        $body = $this->sanitizeAll($data->body) ?? [];
        $attributes = [
            'slug' => $slug,
            'title' => $data->title,
            'excerpt' => $data->excerpt === [] ? null : $data->excerpt,
            'body' => $body === [] ? null : $body,
            'author' => $data->author,
            'tags' => $data->tags === [] ? null : $data->tags,
            'seo' => $data->seo === [] ? null : $data->seo,
            'status' => $data->status,
            'published_at' => $data->publishedAt ?? ($data->status === PublishStatus::Published ? ($post?->published_at ?? now()) : null),
            'reading_minutes' => $this->readingMinutes($body),
        ];

        if ($data->cover !== null) {
            $this->deleteStoredCover($post?->cover_image);
            $attributes['cover_image'] = $data->cover->store(self::COVER_DIRECTORY, self::COVER_DISK);
        } elseif ($data->removeCover) {
            $this->deleteStoredCover($post?->cover_image);
            $attributes['cover_image'] = null;
        }

        return $attributes;
    }

    private function deleteStoredCover(?string $cover): void
    {
        if ($cover !== null && $cover !== '' && ! str_starts_with($cover, '/') && ! str_starts_with($cover, 'http')) {
            Storage::disk(self::COVER_DISK)->delete($cover);
        }
    }

    private function uniqueFaqKey(string $key): string
    {
        $base = $key !== '' ? $key : 'category';
        $candidate = $base;
        $suffix = 2;

        while ($this->faqs->query()->where('key', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }
}
