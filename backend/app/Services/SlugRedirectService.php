<?php

namespace App\Services;

use App\Enums\PublishStatus;
use App\Enums\TripType;
use App\Models\Building;
use App\Models\Package;
use App\Models\Trip;
use App\Repositories\Contracts\SlugRedirectRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 301 redirects for changed slugs (SRS 15.3). Former slugs are stored when a trip, package, building or blog post
 * is renamed; the website resolves an old URL with GET /api/v1/redirects/resolve?path=/en/trips/old-slug and the
 * detail endpoints answer a 404 with "redirect_to" when the slug moved.
 */
class SlugRedirectService
{
    /**
     * Website path segment => morph alias. Nile trips live under /nile, other trips under /trips.
     *
     * @var array<string, string>
     */
    public const SEGMENTS = ['trips' => 'trip', 'nile' => 'trip', 'packages' => 'package', 'buildings' => 'building', 'blog' => 'blog_post'];

    public function __construct(private readonly SlugRedirectRepositoryInterface $redirects) {}

    /**
     * Called when a model's slug changed.
     */
    public function record(Model $model, string $oldSlug, string $newSlug): void
    {
        if ($oldSlug === '' || $oldSlug === $newSlug) {
            return;
        }

        $this->redirects->record($model->getMorphClass(), (int) $model->getKey(), $oldSlug, $newSlug);
    }

    /**
     * Current location of a former slug, or null.
     *
     * @return array{type: string, old_slug: string, slug: string, path: string, status: int}|null
     */
    public function resolve(string $type, string $slug, string $locale = 'en'): ?array
    {
        $redirect = $this->redirects->findByOldSlug($type, $slug);
        $subject = $redirect?->subject;

        if ($subject === null || ! $this->isPublic($subject) || $subject->getAttribute('slug') === $slug) {
            return null;
        }

        return [
            'type' => $type,
            'old_slug' => $slug,
            'slug' => (string) $subject->getAttribute('slug'),
            'path' => '/'.$locale.'/'.$this->segment($subject).'/'.$subject->getAttribute('slug'),
            'status' => 301,
        ];
    }

    /**
     * Resolves a website path such as "/it/nile/old-slug" (locale prefix optional).
     *
     * @return array{type: string, old_slug: string, slug: string, path: string, status: int}|null
     */
    public function resolvePath(string $path): ?array
    {
        $parts = array_values(array_filter(explode('/', (string) parse_url($path, PHP_URL_PATH))));
        $locale = in_array($parts[0] ?? '', ['en', 'it'], true) ? array_shift($parts) : 'en';

        if (count($parts) !== 2 || ! isset(self::SEGMENTS[$parts[0]])) {
            return null;
        }

        return $this->resolve(self::SEGMENTS[$parts[0]], rawurldecode($parts[1]), $locale);
    }

    /**
     * 404 response of a detail endpoint, with "redirect_to" when the slug moved.
     */
    public function notFound(string $type, string $slug, string $message): HttpResponseException
    {
        $redirect = $this->resolve($type, $slug, app()->getLocale() === 'it' ? 'it' : 'en');

        return new HttpResponseException(response()->json(array_filter([
            'message' => $message,
            'redirect_to' => $redirect,
        ]), 404));
    }

    private function segment(Model $subject): string
    {
        return match (true) {
            $subject instanceof Trip => $subject->type === TripType::Nile ? 'nile' : 'trips',
            $subject instanceof Package => 'packages',
            $subject instanceof Building => 'buildings',
            default => 'blog',
        };
    }

    private function isPublic(Model $subject): bool
    {
        return $subject->getAttribute('status') === PublishStatus::Published;
    }
}
