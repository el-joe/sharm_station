<?php

namespace App\Http\Requests\Admin;

use App\DTOs\Pricing\SeasonPriceData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The whole price matrix of a product: base price per nationality group ("base") and every season
 * ("seasons", each for one group or all groups). Overlaps per group are checked by SeasonPriceService.
 */
class SeasonPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-prices');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['seasons' => array_values($this->input('seasons', []))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $price = ['nullable', 'numeric', 'min:0', 'max:999999'];

        return [
            'base' => ['nullable', 'array'],
            'base.*' => ['array'],
            'base.*.adult_price' => $price,
            'base.*.child_price' => $price,
            'base.*.price_per_night' => $price,
            'base.*.price' => $price,
            'base.*.tier_prices' => ['nullable', 'array'],
            'base.*.tier_prices.*' => $price,
            'base.*.unit_prices' => ['nullable', 'array'],
            'base.*.unit_prices.*' => $price,
            'seasons' => ['present', 'array', 'max:120'],
            'seasons.*.id' => ['nullable', 'integer'],
            'seasons.*.nationality_group_id' => ['nullable', 'integer', Rule::exists('nationality_groups', 'id')],
            'seasons.*.name.en' => ['required', 'string', 'max:80'],
            'seasons.*.name.it' => ['nullable', 'string', 'max:80'],
            'seasons.*.starts_on' => ['required', 'date_format:Y-m-d'],
            'seasons.*.ends_on' => ['required', 'date_format:Y-m-d'],
            'seasons.*.recurring_yearly' => ['boolean'],
            'seasons.*.is_active' => ['boolean'],
            'seasons.*.adult_price' => $price,
            'seasons.*.child_price' => $price,
            'seasons.*.price_per_night' => $price,
            'seasons.*.price' => $price,
            'seasons.*.unit_prices' => ['nullable', 'array'],
            'seasons.*.unit_prices.*' => $price,
            'seasons.*.tier_prices' => ['nullable', 'array'],
            'seasons.*.tier_prices.*' => $price,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'seasons.*.name.en' => 'season name',
            'seasons.*.starts_on' => 'from date',
            'seasons.*.ends_on' => 'to date',
            'seasons.*.nationality_group_id' => 'nationality group',
        ];
    }

    /**
     * @return list<array{id: int|null, data: SeasonPriceData}>
     */
    public function rows(): array
    {
        return array_map(fn (array $row): array => [
            'id' => isset($row['id']) ? (int) $row['id'] : null,
            'data' => SeasonPriceData::fromArray([
                ...$row,
                'name' => array_filter($row['name'], fn ($value): bool => filled($value)),
                'recurring_yearly' => (bool) ($row['recurring_yearly'] ?? false),
                'is_active' => (bool) ($row['is_active'] ?? false),
            ]),
        ], $this->validated('seasons'));
    }

    /**
     * Base prices keyed by nationality group id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function baseRows(): array
    {
        return $this->validated('base') ?? [];
    }
}
