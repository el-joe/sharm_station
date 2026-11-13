<?php

namespace App\Models;

use Database\Factories\GoogleReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['place_id', 'google_review_id', 'author_name', 'author_photo_url', 'author_url', 'author_country', 'rating', 'text', 'language', 'published_at', 'is_hidden', 'synced_at'])]
class GoogleReview extends Model
{
    /** @use HasFactory<GoogleReviewFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_hidden' => 'boolean',
            'published_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('is_hidden', false);
    }
}
