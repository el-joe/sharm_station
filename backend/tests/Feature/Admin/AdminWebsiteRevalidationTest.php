<?php

use App\Enums\UserRole;
use App\Models\BlogPost;
use App\Models\FaqCategory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

const REVALIDATE_URL = 'http://frontend.test/api/revalidate';

beforeEach(function () {
    $this->withoutVite();
    $this->editor = staffUser(UserRole::Editor);
    config(['services.frontend.revalidate_url' => REVALIDATE_URL, 'services.frontend.revalidate_secret' => 'test-secret']);
});

/**
 * @return list<list<string>>
 */
function sentRevalidationTags(): array
{
    return Http::recorded()->map(fn (array $pair): array => $pair[0]['tags'])->values()->all();
}

test('saving a page revalidates the content, pages and page tags with the secret header', function () {
    Http::fake([REVALIDATE_URL => Http::response(['revalidated' => true])]);

    $this->actingAs($this->editor)->put(route('admin.content.pages.update', 'about'), [
        'title' => ['en' => 'About us'],
        'body' => ['en' => '<p>Since 2005.</p>'],
    ])->assertRedirect();

    Http::assertSent(fn (Request $request): bool => $request->url() === REVALIDATE_URL
        && $request->method() === 'POST'
        && $request->hasHeader('x-revalidate-secret', 'test-secret')
        && $request['tags'] === ['content', 'pages', 'page:about']);
});

test('blog posts revalidate the blog tags, including the old slug after a rename and on delete', function () {
    Http::fake([REVALIDATE_URL => Http::response()]);
    $post = BlogPost::factory()->create(['slug' => 'old-slug']);

    $this->actingAs($this->editor)->put(route('admin.content.posts.update', $post), ['title' => ['en' => 'New title'], 'slug' => 'new-slug', 'status' => 'draft'])->assertRedirect();
    $this->actingAs($this->editor)->delete(route('admin.content.posts.destroy', $post->fresh()))->assertRedirect();

    expect(sentRevalidationTags())->toBe([
        ['content', 'blog', 'blog:old-slug', 'blog:new-slug'],
        ['content', 'blog', 'blog:new-slug'],
    ]);
});

test('FAQ changes revalidate the faqs tag', function () {
    Http::fake([REVALIDATE_URL => Http::response()]);

    $this->actingAs($this->editor)->post(route('admin.content.faq.categories.store'), ['title' => ['en' => 'Diving']])->assertRedirect();
    $this->actingAs($this->editor)->delete(route('admin.content.faq.categories.destroy', FaqCategory::query()->sole()))->assertRedirect();

    expect(sentRevalidationTags())->toBe([['content', 'faqs'], ['content', 'faqs']]);
});

test('an unreachable website never breaks the admin save and the failure is logged', function () {
    Log::spy();
    Http::fake([REVALIDATE_URL => fn () => throw new ConnectionException('Connection refused')]);

    $this->actingAs($this->editor)->post(route('admin.content.faq.categories.store'), ['title' => ['en' => 'Diving']])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(FaqCategory::query()->count())->toBe(1);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => $message === 'Website revalidation failed.')->once();
});

test('nothing is sent while the revalidation secret is not configured', function () {
    config(['services.frontend.revalidate_secret' => null]);
    Http::fake();

    $this->actingAs($this->editor)->post(route('admin.content.faq.categories.store'), ['title' => ['en' => 'Diving']])->assertRedirect();

    Http::assertNothingSent();
});
