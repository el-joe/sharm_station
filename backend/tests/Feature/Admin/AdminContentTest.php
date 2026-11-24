<?php

use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Models\BlogPost;
use App\Models\FaqCategory;
use App\Models\FaqItem;
use App\Models\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutVite();
    $this->editor = staffUser(UserRole::Editor);
});

test('editors save a fixed page and unsafe HTML is removed', function () {
    $this->actingAs($this->editor)->get(route('admin.content.pages.edit', 'privacy'))->assertOk()->assertSee('Privacy notice');

    $this->actingAs($this->editor)->put(route('admin.content.pages.update', 'privacy'), [
        'title' => ['en' => 'Privacy notice', 'it' => 'Informativa privacy'],
        'intro' => ['en' => 'How we use your data.'],
        'body' => [
            'en' => '<h2 id="data" onclick="steal()">Data</h2><p>We keep <strong>little</strong>.<script>alert(1)</script></p><a href="javascript:alert(1)">x</a><iframe src="https://evil.test"></iframe>',
            'it' => '<p>Teniamo <em>poco</em>.</p>',
        ],
        'seo' => ['en' => ['title' => 'Privacy | Sharm Station', 'description' => 'Privacy notice']],
    ])->assertRedirect(route('admin.content.pages.edit', 'privacy'));

    $page = Page::query()->where('slug', 'privacy')->firstOrFail();
    expect($page->getTranslations('body'))->toBe(['en' => '<h2 id="data">Data</h2><p>We keep <strong>little</strong>.</p><a>x</a>', 'it' => '<p>Teniamo <em>poco</em>.</p>'])
        ->and($page->getTranslations('seo')['en']['title'])->toBe('Privacy | Sharm Station');

    $this->actingAs($this->editor)->get(route('admin.content.pages.edit', 'not-a-page'))->assertNotFound();
});

test('blog posts get a slug, a computed reading time and an uploaded cover', function () {
    Storage::fake('public');
    $body = '<p>'.str_repeat('word ', 450).'</p>';

    $this->actingAs($this->editor)->post(route('admin.content.posts.store'), [
        'title' => ['en' => 'Snorkelling in Ras Mohammed', 'it' => 'Snorkeling a Ras Mohammed'],
        'excerpt' => ['en' => 'The best reefs.'],
        'body' => ['en' => $body, 'it' => '<p>Breve.</p>'],
        'tags' => ['en' => 'Red Sea, Guide , Red Sea', 'it' => 'Mar Rosso'],
        'author' => 'Sara',
        'status' => 'published',
        'cover' => UploadedFile::fake()->image('cover.jpg', 1200, 800),
    ])->assertRedirect();

    $post = BlogPost::query()->firstOrFail();
    expect($post->slug)->toBe('snorkelling-in-ras-mohammed')
        ->and($post->reading_minutes)->toBe(3)
        ->and($post->status)->toBe(PublishStatus::Published)
        ->and($post->published_at)->not->toBeNull()
        ->and($post->getTranslations('tags'))->toBe(['en' => ['Red Sea', 'Guide'], 'it' => ['Mar Rosso']]);
    Storage::disk('public')->assertExists($post->cover_image);

    $this->actingAs($this->editor)->getJson(route('admin.content.blog.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertOk()->assertJsonPath('recordsTotal', 1);

    $this->actingAs($this->editor)->post(route('admin.content.posts.store'), ['title' => ['en' => 'Snorkelling in Ras Mohammed'], 'status' => 'draft'])
        ->assertSessionHasErrors('slug');

    $old = $post->cover_image;
    $this->actingAs($this->editor)->put(route('admin.content.posts.update', $post), ['title' => ['en' => 'Snorkelling'], 'slug' => 'snorkelling', 'status' => 'draft', 'remove_cover' => '1'])
        ->assertRedirect(route('admin.content.posts.edit', $post));
    Storage::disk('public')->assertMissing($old);
    expect($post->fresh()->cover_image)->toBeNull()->and($post->fresh()->slug)->toBe('snorkelling');

    $this->actingAs($this->editor)->delete(route('admin.content.posts.destroy', $post))->assertRedirect();
    expect(BlogPost::query()->count())->toBe(0);
});

test('FAQ categories and questions are created, sanitized and reordered', function () {
    $this->actingAs($this->editor)->post(route('admin.content.faq.categories.store'), ['title' => ['en' => 'Diving', 'it' => 'Immersioni']])->assertRedirect();
    $category = FaqCategory::query()->where('key', 'diving')->firstOrFail();

    $this->actingAs($this->editor)->post(route('admin.content.faq.items.store'), [
        'faq_category_id' => $category->id, 'question' => ['en' => 'Do I need a licence?'], 'answer' => ['en' => '<p onmouseover="x()">No.</p>'], 'is_active' => '1',
    ])->assertRedirect();
    $first = FaqItem::query()->firstOrFail();
    expect($first->getTranslations('answer'))->toBe(['en' => '<p>No.</p>']);

    $second = FaqItem::factory()->for($category, 'category')->create(['sort' => 5]);
    $other = FaqCategory::factory()->create(['sort' => 9]);

    $this->actingAs($this->editor)->postJson(route('admin.content.faq.reorder'), ['categories' => [$other->id, $category->id], 'items' => [$category->id => [$second->id, $first->id]]])
        ->assertOk();

    expect($other->fresh()->sort)->toBe(0)->and($category->fresh()->sort)->toBe(1)
        ->and($second->fresh()->sort)->toBe(0)->and($first->fresh()->sort)->toBe(1);

    $this->actingAs($this->editor)->get(route('admin.content.index'))->assertOk()->assertSee('Do I need a licence?');
    $this->actingAs($this->editor)->get(route('admin.content.faq.items.edit', $first))->assertOk();
    $this->actingAs($this->editor)->delete(route('admin.content.faq.categories.destroy', $category))->assertRedirect();
    expect(FaqItem::query()->count())->toBe(0);
});
