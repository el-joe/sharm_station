<?php

namespace App\DTOs\Partner;

use App\Enums\CommissionCategory;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Partners x commission types override matrix (CR-11). Empty cells remove the override (global setting applies).
 */
final readonly class CommissionMatrixData
{
    /**
     * @param  array<int, array{categories: array<string, float|null>}>  $rows  keyed by partner id
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

            $rows[(int) $partnerId] = ['categories' => $categories];
        }

        return new self($rows);
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }
}
