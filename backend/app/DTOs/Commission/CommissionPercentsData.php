<?php

namespace App\DTOs\Commission;

use App\Enums\CommissionCategory;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Percentages per commission type: global settings (every type set) or partner overrides (null = use the global setting).
 */
final readonly class CommissionPercentsData
{
    /**
     * @param  array<string, float|null>  $percents  category value => percent
     */
    public function __construct(public array $percents) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        $percents = [];

        foreach (CommissionCategory::cases() as $category) {
            $value = $values[$category->value] ?? null;
            $percents[$category->value] = $value === null || $value === '' ? null : round((float) $value, 2);
        }

        return new self($percents);
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated('commissions') ?? []);
    }
}
