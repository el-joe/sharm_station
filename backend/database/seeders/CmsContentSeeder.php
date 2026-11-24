<?php

namespace Database\Seeders;

use App\Enums\PublishStatus;
use App\Models\BlogPost;
use App\Models\FaqCategory;
use App\Models\Page;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Pages, blog posts and FAQ copied from the website texts (frontend/lib/content/*.ts, exported to
 * database/seeders/data/cms-content.json) so the site looks the same when it reads them from the API.
 * Idempotent: records that already exist (edited in the admin) are left untouched.
 */
class CmsContentSeeder extends Seeder
{
    public const DATA_FILE = 'seeders/data/cms-content.json';

    public function run(): void
    {
        $data = json_decode(File::get(database_path(self::DATA_FILE)), true, flags: JSON_THROW_ON_ERROR);

        $this->seedPages($data['pages']);
        $this->seedPosts($data['blog']);
        $this->seedFaq($data['faq']);
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $pages  slug => locale => doc
     */
    private function seedPages(array $pages): void
    {
        foreach ($pages as $slug => $locales) {
            if (Page::query()->where('slug', $slug)->exists()) {
                continue;
            }

            $page = new Page(['slug' => $slug]);
            $page->forceFill([
                'title' => $this->perLocale($locales, fn (array $doc): string => $doc['title']),
                'intro' => $this->perLocale($locales, fn (array $doc): string => $doc['intro']),
                'body' => $this->perLocale($locales, fn (array $doc): string => $this->sectionsHtml($doc['sections'])),
                'seo' => $this->perLocale($locales, fn (array $doc): array => ['title' => $doc['title'], 'description' => $doc['description']]),
            ]);
            $updated = CarbonImmutable::parse(reset($locales)['updated'] ?? 'now');
            $page->created_at = $updated;
            $page->updated_at = $updated;
            $page->timestamps = false;
            $page->save();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $posts
     */
    private function seedPosts(array $posts): void
    {
        foreach ($posts as $post) {
            if (BlogPost::query()->where('slug', $post['slug'])->exists()) {
                continue;
            }

            $locales = $post['locales'];

            BlogPost::query()->create([
                'slug' => $post['slug'],
                'title' => $this->perLocale($locales, fn (array $doc): string => $doc['title']),
                'excerpt' => $this->perLocale($locales, fn (array $doc): string => $doc['excerpt']),
                'body' => $this->perLocale($locales, fn (array $doc): string => $this->sectionsHtml($doc['sections'])),
                'tags' => $this->perLocale($locales, fn (array $doc): array => $doc['tags']),
                'seo' => $this->perLocale($locales, fn (array $doc): array => ['title' => $doc['title'], 'description' => $doc['excerpt']]),
                'cover_image' => $post['image'],
                'author' => $post['author'],
                'related' => $post['related'] ?? [],
                'status' => PublishStatus::Published,
                'published_at' => CarbonImmutable::parse($post['published'])->setTime(9, 0),
                'reading_minutes' => $post['readMinutes'],
            ]);
        }
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $faq  locale => categories (same order and ids in every locale)
     */
    private function seedFaq(array $faq): void
    {
        $english = $faq['en'];

        foreach ($english as $sort => $category) {
            if (FaqCategory::query()->where('key', $category['id'])->exists()) {
                continue;
            }

            $translations = collect($faq)->map(fn (array $categories) => collect($categories)->firstWhere('id', $category['id']))->filter();

            $model = FaqCategory::query()->create([
                'key' => $category['id'],
                'title' => $translations->map(fn (array $translated): string => $translated['title'])->all(),
                'sort' => $sort,
            ]);

            foreach ($category['items'] as $index => $item) {
                $model->items()->create([
                    'question' => $translations->map(fn (array $translated): string => $translated['items'][$index]['question'] ?? $item['question'])->all(),
                    'answer' => $translations->map(fn (array $translated): string => '<p>'.e($translated['items'][$index]['answer'] ?? $item['answer']).'</p>')->all(),
                    'sort' => $index,
                    'is_active' => true,
                ]);
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $locales
     * @return array<string, mixed>
     */
    private function perLocale(array $locales, callable $value): array
    {
        return collect($locales)->map(fn (array $doc) => $value($doc))->all();
    }

    /**
     * Sections ({id, heading, paragraphs, list?}) as HTML: an anchored h2, paragraphs and an optional list.
     *
     * @param  list<array{id: string, heading: string, paragraphs: list<string>, list?: list<string>}>  $sections
     */
    private function sectionsHtml(array $sections): string
    {
        return collect($sections)->map(function (array $section): string {
            $html = '<h2 id="'.e($section['id']).'">'.e($section['heading']).'</h2>';

            foreach ($section['paragraphs'] as $paragraph) {
                $html .= '<p>'.e($paragraph).'</p>';
            }

            if (! empty($section['list'])) {
                $html .= '<ul>'.collect($section['list'])->map(fn (string $item): string => '<li>'.e($item).'</li>')->implode('').'</ul>';
            }

            return $html;
        })->implode("\n");
    }
}
