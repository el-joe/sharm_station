<?php

namespace App\DTOs\Content;

/**
 * Normalises {locale: value} form input: trims and drops empty locales.
 */
final class TranslatableInput
{
    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    public static function texts(array $values): array
    {
        $texts = [];

        foreach ($values as $locale => $value) {
            if (is_string($value) && trim($value) !== '') {
                $texts[(string) $locale] = trim($value);
            }
        }

        return $texts;
    }

    /**
     * @param  array<string, mixed>  $values  {locale: {title, description}}
     * @return array<string, array{title: string|null, description: string|null}>
     */
    public static function seo(array $values): array
    {
        $seo = [];

        foreach ($values as $locale => $value) {
            $title = is_array($value) && filled($value['title'] ?? null) ? trim((string) $value['title']) : null;
            $description = is_array($value) && filled($value['description'] ?? null) ? trim((string) $value['description']) : null;

            if ($title !== null || $description !== null) {
                $seo[(string) $locale] = ['title' => $title, 'description' => $description];
            }
        }

        return $seo;
    }

    /**
     * Comma-separated tags per locale.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, list<string>>
     */
    public static function tags(array $values): array
    {
        $tags = [];

        foreach ($values as $locale => $value) {
            $list = is_array($value) ? $value : explode(',', (string) $value);
            $list = array_values(array_unique(array_filter(array_map(fn ($tag): string => trim((string) $tag), $list), fn (string $tag): bool => $tag !== '')));

            if ($list !== []) {
                $tags[(string) $locale] = $list;
            }
        }

        return $tags;
    }
}
