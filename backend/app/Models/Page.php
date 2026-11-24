<?php

namespace App\Models;

use App\Enums\PageSlug;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Static website page with a fixed slug (about, booking terms, privacy, cancellation policy, cookie policy).
 * "body" holds sanitized HTML per locale; "seo" holds {locale: {title, description}}.
 */
#[Fillable(['slug', 'title', 'intro', 'body', 'seo'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasTranslations, LogsActivity;

    /**
     * @var list<string>
     */
    protected array $translatable = ['title', 'intro', 'body', 'seo'];

    public function pageSlug(): ?PageSlug
    {
        return PageSlug::tryFrom($this->slug);
    }
}
