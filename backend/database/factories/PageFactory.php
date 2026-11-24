<?php

namespace Database\Factories;

use App\Enums\PageSlug;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => PageSlug::About->value,
            'title' => ['en' => 'About us', 'it' => 'Chi siamo'],
            'intro' => ['en' => 'Who we are.', 'it' => 'Chi siamo.'],
            'body' => ['en' => '<h2 id="story">Our story</h2><p>Since 2010.</p>', 'it' => '<h2 id="story">La nostra storia</h2><p>Dal 2010.</p>'],
            'seo' => null,
        ];
    }
}
