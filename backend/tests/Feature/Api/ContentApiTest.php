<?php

use App\Models\BlogPost;
use App\Models\FaqCategory;
use App\Models\FaqItem;
use App\Models\Page;
use App\Models\Setting;
use Database\Seeders\CmsContentSeeder;
use Database\Seeders\OfficeLocationSeeder;

test('a fixed page is served in the request locale with an English fallback', function () {
    Page::factory()->create(['title' => ['en' => 'About us', 'it' => 'Chi siamo'], 'intro' => ['en' => 'Who we are.'], 'seo' => ['it' => ['title' => 'Chi siamo | Sharm', 'description' => null]]]);

    $this->getJson('/api/v1/pages/about?locale=it')
        ->assertOk()
        ->assertJsonPath('data.slug', 'about')
        ->assertJsonPath('data.title', 'Chi siamo')
        ->assertJsonPath('data.intro', 'Who we are.')
        ->assertJsonPath('data.body_html', '<h2 id="story">La nostra storia</h2><p>Dal 2010.</p>')
        ->assertJsonPath('data.seo.title', 'Chi siamo | Sharm')
        ->assertJsonStructure(['data' => ['slug', 'title', 'intro', 'body_html', 'seo' => ['title', 'description'], 'updated_at']]);

    $this->getJson('/api/v1/pages/terms-of-war')->assertNotFound();
    $this->getJson('/api/v1/pages/privacy')->assertNotFound();
});

test('the blog lists only published posts, newest first, and hides drafts and scheduled posts', function () {
    BlogPost::factory()->create(['slug' => 'older', 'published_at' => now()->subDays(10)]);
    BlogPost::factory()->create(['slug' => 'newer', 'published_at' => now()->subDay(), 'cover_image' => 'blog/cover.webp']);
    BlogPost::factory()->draft()->create(['slug' => 'draft']);
    BlogPost::factory()->create(['slug' => 'scheduled', 'published_at' => now()->addWeek()]);

    $this->getJson('/api/v1/blog')
        ->assertOk()
        ->assertJsonPath('data.*.slug', ['newer', 'older'])
        ->assertJsonPath('data.0.cover_image', fn (string $url) => str_ends_with($url, '/storage/blog/cover.webp'))
        ->assertJsonPath('meta.total', 2)
        ->assertJsonStructure(['data' => [['slug', 'title', 'excerpt', 'cover_image', 'body_html', 'author', 'published_at', 'tags', 'reading_minutes', 'seo']]]);

    $this->getJson('/api/v1/blog/newer?locale=it')->assertOk()->assertJsonPath('data.tags', ['Guida']);
    $this->getJson('/api/v1/blog/draft')->assertNotFound();
    $this->getJson('/api/v1/blog/scheduled')->assertNotFound();
});

test('FAQ is grouped by category in order, without hidden questions or empty categories', function () {
    $payments = FaqCategory::factory()->create(['key' => 'payments', 'title' => ['en' => 'Payments'], 'sort' => 2]);
    $booking = FaqCategory::factory()->create(['key' => 'booking', 'title' => ['en' => 'Booking', 'it' => 'Prenotazione'], 'sort' => 1]);
    FaqCategory::factory()->create(['key' => 'empty', 'sort' => 3]);
    FaqItem::factory()->for($booking, 'category')->create(['question' => ['en' => 'Second?'], 'sort' => 2]);
    FaqItem::factory()->for($booking, 'category')->create(['question' => ['en' => 'First?', 'it' => 'Primo?'], 'answer' => ['en' => '<p>Yes.</p>'], 'sort' => 1]);
    FaqItem::factory()->for($booking, 'category')->create(['is_active' => false]);
    FaqItem::factory()->for($payments, 'category')->create();

    $this->getJson('/api/v1/faqs?locale=it')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.category', 'Prenotazione')
        ->assertJsonPath('data.0.items.*.question', ['Primo?', 'Second?'])
        ->assertJsonPath('data.0.items.0.answer_html', '<p>Yes.</p>');
});

test('the content seeder copies the website texts once and keeps admin edits', function () {
    $this->seed(CmsContentSeeder::class);

    expect(Page::query()->count())->toBe(5)
        ->and(BlogPost::query()->count())->toBe(2)
        ->and(FaqCategory::query()->count())->toBe(5)
        ->and(FaqItem::query()->count())->toBe(14);

    $this->getJson('/api/v1/pages/cancellation-policy')
        ->assertJsonPath('data.title', 'Cancellation policy')
        ->assertJsonPath('data.body_html', fn (string $html) => str_contains($html, '<li>More than 7 days before: full refund of what you paid.</li>'));
    $this->getJson('/api/v1/blog/first-trip-to-egypt-8-days?locale=it')
        ->assertJsonPath('data.title', 'Il primo viaggio in Egitto: un itinerario di 8 giorni')
        ->assertJsonPath('data.reading_minutes', 7)
        ->assertJsonPath('data.cover_image', '/images/cairo.webp')
        ->assertJsonPath('data.author', 'Sara Mahmoud');

    Page::query()->where('slug', 'about')->firstOrFail()->update(['title' => ['en' => 'Edited']]);
    $this->seed(CmsContentSeeder::class);

    expect(Page::query()->count())->toBe(5)
        ->and(FaqItem::query()->count())->toBe(14)
        ->and(Page::query()->where('slug', 'about')->firstOrFail()->getTranslations('title'))->toBe(['en' => 'Edited']);
});

test('meta exposes the office contact for the map and keeps the existing keys', function () {
    seedCurrencies();
    Setting::factory()->create(['key' => 'site.phone', 'value' => '+20 69 360 0000']);
    $this->seed(OfficeLocationSeeder::class);

    $this->getJson('/api/v1/meta')
        ->assertOk()
        ->assertJsonPath('data.contact.lat', 27.9158)
        ->assertJsonPath('data.contact.lng', 34.33)
        ->assertJsonPath('data.contact.address', 'Naama Bay, Sharm El Sheikh, Egypt')
        ->assertJsonPath('data.contact.phone', '+20 69 360 0000')
        ->assertJsonStructure(['data' => ['locale', 'currencies', 'destinations', 'site', 'contact' => ['address', 'lat', 'lng', 'phone', 'email', 'whatsapp']]]);
});
