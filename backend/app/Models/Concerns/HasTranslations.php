<?php

namespace App\Models\Concerns;

/**
 * Translatable JSON columns stored as {"en": "...", "it": "..."}.
 *
 * Reading the attribute returns the value for the current locale with an
 * English fallback; getTranslations() returns the raw map.
 *
 * Models must declare: protected array $translatable = ['title', ...];
 */
trait HasTranslations
{
    public const FALLBACK_LOCALE = 'en';

    public function initializeHasTranslations(): void
    {
        foreach ($this->getTranslatableAttributes() as $attribute) {
            $this->mergeCasts([$attribute => 'array']);
        }
    }

    /**
     * @return list<string>
     */
    public function getTranslatableAttributes(): array
    {
        return property_exists($this, 'translatable') ? $this->translatable : [];
    }

    public function isTranslatableAttribute(string $key): bool
    {
        return in_array($key, $this->getTranslatableAttributes(), true);
    }

    public function getAttributeValue($key): mixed
    {
        if (! $this->isTranslatableAttribute($key)) {
            return parent::getAttributeValue($key);
        }

        return $this->translate($key);
    }

    public function setAttribute($key, $value): mixed
    {
        if ($this->isTranslatableAttribute($key) && ! is_array($value) && $value !== null) {
            $value = [app()->getLocale() => $value];
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Value of a translatable attribute for the given (or current) locale, falling back to English.
     */
    public function translate(string $key, ?string $locale = null): mixed
    {
        $translations = $this->getTranslations($key);

        if ($translations === []) {
            return null;
        }

        $locale ??= app()->getLocale();

        foreach ([$locale, self::FALLBACK_LOCALE] as $candidate) {
            if (array_key_exists($candidate, $translations) && $translations[$candidate] !== null && $translations[$candidate] !== '' && $translations[$candidate] !== []) {
                return $translations[$candidate];
            }
        }

        return reset($translations);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTranslations(string $key): array
    {
        $value = parent::getAttributeValue($key);

        return is_array($value) ? $value : [];
    }

    public function setTranslation(string $key, string $locale, mixed $value): static
    {
        $translations = $this->getTranslations($key);
        $translations[$locale] = $value;

        parent::setAttribute($key, $translations);

        return $this;
    }
}
