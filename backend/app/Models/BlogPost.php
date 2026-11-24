<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'title', 'excerpt', 'body', 'cover_image', 'author', 'tags', 'related', 'seo', 'status', 'published_at', 'reading_minutes'])]
class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory, HasTranslations, LogsActivity;

    /**
     * @var list<string>
     */
    protected array $translatable = ['title', 'excerpt', 'body', 'tags', 'seo'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'published_at' => 'immutable_datetime',
            'reading_minutes' => 'integer',
            'related' => 'array',
        ];
    }

    /**
     * Published and not scheduled in the future.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', PublishStatus::Published)
            ->where(fn (Builder $query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function isPublished(): bool
    {
        return $this->status === PublishStatus::Published && ($this->published_at === null || $this->published_at->isPast());
    }
}
