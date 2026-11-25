<?php

namespace App\Models\Concerns;

use App\Services\SlugRedirectService;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps former slugs as 301 redirects when the slug changes (SRS 15.3).
 */
trait RecordsSlugRedirects
{
    public static function bootRecordsSlugRedirects(): void
    {
        static::updated(function (Model $model): void {
            if ($model->wasChanged('slug')) {
                app(SlugRedirectService::class)->record($model, (string) $model->getOriginal('slug'), (string) $model->getAttribute('slug'));
            }
        });
    }
}
