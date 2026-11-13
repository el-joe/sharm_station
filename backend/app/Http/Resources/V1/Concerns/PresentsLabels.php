<?php

namespace App\Http\Resources\V1\Concerns;

use Illuminate\Support\Str;

/**
 * Localized labels for catalogue codes (lang/{locale}/catalog.php), falling back to a headline of the code.
 */
trait PresentsLabels
{
    protected function catalogLabel(string $group, ?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        $key = "catalog.{$group}.{$code}";
        $label = __($key);

        return $label === $key ? Str::headline($code) : $label;
    }

    /**
     * @param  list<string>|null  $codes
     * @return list<array{code: string, label: string}>
     */
    protected function catalogLabels(string $group, ?array $codes): array
    {
        return array_values(array_map(fn (string $code): array => ['code' => $code, 'label' => (string) $this->catalogLabel($group, $code)], $codes ?? []));
    }
}
