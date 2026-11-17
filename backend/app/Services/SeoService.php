<?php

namespace App\Services;

use App\DTOs\Ai\SeoValuesData;
use App\Enums\SeoSubjectType;
use App\Exceptions\DomainValidationException;
use App\Http\Middleware\SetLocaleAndCurrency;
use App\Repositories\Contracts\BaseRepositoryInterface;
use App\Repositories\Contracts\BuildingRepositoryInterface;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Repositories\Contracts\TransferFixedRouteRepositoryInterface;
use App\Repositories\Contracts\TripRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * SEO of trips, packages, transfer routes, buildings and rooms.
 *
 * Stored in each product's `seo` JSON column:
 *   {"title": "...", "description": "...",                     legacy English meta (admin SEO tab)
 *    "locales": {"en": {"meta_title", "meta_description", "keywords", "og_title", "og_description"}, "it": {...}}}
 * The legacy keys always mirror locales.en.meta_title / meta_description.
 */
class SeoService
{
    public function __construct(
        private readonly TripRepositoryInterface $trips,
        private readonly PackageRepositoryInterface $packages,
        private readonly TransferFixedRouteRepositoryInterface $routes,
        private readonly BuildingRepositoryInterface $buildings,
        private readonly RoomRepositoryInterface $rooms,
    ) {}

    public function findSubject(SeoSubjectType $type, int $id): ?Model
    {
        return $this->repository($type)->find($id);
    }

    /**
     * Current SEO values of a product, per locale (every supported locale present).
     */
    public function current(Model $subject): SeoValuesData
    {
        $seo = (array) ($subject->getAttribute('seo') ?? []);
        $locales = [];

        foreach (SetLocaleAndCurrency::LOCALES as $locale) {
            $stored = (array) ($seo['locales'][$locale] ?? []);

            if ($locale === 'en') {
                $stored['meta_title'] ??= $seo['title'] ?? null;
                $stored['meta_description'] ??= $seo['description'] ?? null;
            }

            foreach (SeoValuesData::FIELDS as $field) {
                $locales[$locale][$field] = $stored[$field] ?? null;
            }
        }

        $slug = SeoSubjectType::fromModel($subject)->hasSlug() ? $subject->getAttribute('slug') : null;

        return new SeoValuesData($locales, is_string($slug) ? $slug : null);
    }

    /**
     * Applies the given values (null/missing fields stay unchanged) and the slug, if the product has one.
     *
     * @throws DomainValidationException when a slug is given for a product without slug or the slug is taken.
     */
    public function apply(Model $subject, SeoValuesData $values): Model
    {
        $type = SeoSubjectType::fromModel($subject);
        $seo = (array) ($subject->getAttribute('seo') ?? []);

        foreach ($values->locales as $locale => $fields) {
            if (! in_array($locale, SetLocaleAndCurrency::LOCALES, true)) {
                continue;
            }

            $seo['locales'][$locale] = array_replace((array) ($seo['locales'][$locale] ?? []), array_filter($fields, fn ($value): bool => $value !== null && $value !== []));
        }

        $attributes = ['seo' => self::syncLegacyKeys($seo, fromLocales: true)];

        if ($values->slug !== null && $values->slug !== $subject->getAttribute('slug')) {
            if (! $type->hasSlug()) {
                throw DomainValidationException::withErrors(['slug' => ["A {$type->label()} has no URL slug."]]);
            }

            if ($this->repository($type)->query()->where('slug', $values->slug)->whereKeyNot($subject->getKey())->exists()) {
                throw DomainValidationException::withErrors(['slug' => ["The slug \"{$values->slug}\" is already used by another {$type->label()}."]]);
            }

            $attributes['slug'] = $values->slug;
        }

        return $this->repository($type)->update($subject, $attributes);
    }

    /**
     * Merges an SEO payload from an admin product form (legacy title/description only) into the stored SEO,
     * so the per-locale values and sharing text survive a product save.
     *
     * @param  array<string, mixed>|null  $stored
     * @param  array<string, mixed>|null  $incoming
     * @return array<string, mixed>|null
     */
    public static function mergeFormSeo(?array $stored, ?array $incoming): ?array
    {
        if ($incoming === null) {
            return $stored;
        }

        return self::syncLegacyKeys(array_replace($stored ?? [], $incoming), fromLocales: false);
    }

    /**
     * Keeps seo.title/description and seo.locales.en.meta_title/meta_description equal.
     *
     * @param  array<string, mixed>  $seo
     * @return array<string, mixed>
     */
    private static function syncLegacyKeys(array $seo, bool $fromLocales): array
    {
        if ($fromLocales) {
            $seo['title'] = $seo['locales']['en']['meta_title'] ?? ($seo['title'] ?? null);
            $seo['description'] = $seo['locales']['en']['meta_description'] ?? ($seo['description'] ?? null);

            return $seo;
        }

        if (isset($seo['locales']['en']) || filled($seo['title'] ?? null) || filled($seo['description'] ?? null)) {
            $seo['locales']['en']['meta_title'] = $seo['title'] ?? null;
            $seo['locales']['en']['meta_description'] = $seo['description'] ?? null;
        }

        return $seo;
    }

    /**
     * @return BaseRepositoryInterface<Model>
     */
    private function repository(SeoSubjectType $type): BaseRepositoryInterface
    {
        return match ($type) {
            SeoSubjectType::Trip => $this->trips,
            SeoSubjectType::Package => $this->packages,
            SeoSubjectType::TransferRoute => $this->routes,
            SeoSubjectType::Building => $this->buildings,
            SeoSubjectType::Room => $this->rooms,
        };
    }
}
