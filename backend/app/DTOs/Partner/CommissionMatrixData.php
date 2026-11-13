<?php

namespace App\DTOs\Partner;

use App\Enums\CommissionCategory;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Partners x categories commission matrix (SRS 8.5). Empty cells remove the category override.
 */
final readonly class CommissionMatrixData
{
    /**
     * @param  array<int, array{default: float|null, categories: array<string, float|null>}>  $rows  keyed by partner id
     */
    public function __construct(public array $rows) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $rows = [];
        $percent = fn ($value): ?float => $value === null || $value === '' ? null : round((float) $value, 2);

        foreach ($data['partners'] ?? [] as $partnerId => $row) {
            $categories = [];

            foreach (CommissionCategory::cases() as $category) {
                $categories[$category->value] = $percent($row['categories'][$category->value] ?? null);
            }

            $rows[(int) $partnerId] = ['default' => $percent($row['default'] ?? null), 'categories' => $categories];
        }

        return new self($rows);
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }
}
