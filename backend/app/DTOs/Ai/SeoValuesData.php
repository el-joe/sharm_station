<?php

namespace App\DTOs\Ai;

/**
 * SEO values of one product: optional slug plus per-locale meta and sharing text.
 *
 * Locale entries hold meta_title, meta_description, keywords (list of strings), og_title, og_description;
 * a missing or null field means "unchanged" when applied.
 */
final readonly class SeoValuesData
{
    public const FIELDS = ['meta_title', 'meta_description', 'keywords', 'og_title', 'og_description'];

    /**
     * @param  array<string, array<string, string|list<string>|null>>  $locales
     */
    public function __construct(
        public array $locales,
        public ?string $slug = null,
    ) {}

    /**
     * @param  array{slug?: string|null, locales?: array<string, array<string, mixed>>}  $data
     */
    public static function fromArray(array $data): self
    {
        $locales = [];

        foreach ((array) ($data['locales'] ?? []) as $locale => $values) {
            $clean = [];

            foreach (self::FIELDS as $field) {
                $value = $values[$field] ?? null;

                if ($field === 'keywords') {
                    $value = self::keywords($value);
                } elseif (is_string($value)) {
                    $value = trim($value) === '' ? null : trim($value);
                }

                if ($value !== null && $value !== []) {
                    $clean[$field] = $value;
                }
            }

            if ($clean !== []) {
                $locales[(string) $locale] = $clean;
            }
        }

        $slug = isset($data['slug']) && is_string($data['slug']) && trim($data['slug']) !== '' ? trim($data['slug']) : null;

        return new self($locales, $slug);
    }

    /**
     * Keywords from a list or a comma separated string, trimmed, without blanks or duplicates.
     *
     * @return list<string>|null
     */
    public static function keywords(mixed $value): ?array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return null;
        }

        $keywords = array_values(array_unique(array_filter(array_map(fn ($keyword): string => trim((string) $keyword), $value), fn (string $keyword): bool => $keyword !== '')));

        return $keywords === [] ? null : $keywords;
    }

    public function isEmpty(): bool
    {
        return $this->locales === [] && $this->slug === null;
    }

    /**
     * @return array{slug: string|null, locales: array<string, array<string, string|list<string>|null>>}
     */
    public function toArray(): array
    {
        return ['slug' => $this->slug, 'locales' => $this->locales];
    }
}
