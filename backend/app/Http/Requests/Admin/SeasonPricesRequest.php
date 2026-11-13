<?php

namespace App\Http\Requests\Admin;

use App\DTOs\Pricing\SeasonPriceData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The whole price list of a trip or room (SeasonPriceEditor). Overlaps are checked by SeasonPriceService.
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
        return [
            'seasons' => ['present', 'array', 'max:60'],
            'seasons.*.id' => ['nullable', 'integer'],
            'seasons.*.name.en' => ['required', 'string', 'max:80'],
            'seasons.*.name.it' => ['nullable', 'string', 'max:80'],
            'seasons.*.starts_on' => ['required', 'date_format:Y-m-d'],
            'seasons.*.ends_on' => ['required', 'date_format:Y-m-d'],
            'seasons.*.recurring_yearly' => ['boolean'],
            'seasons.*.is_active' => ['boolean'],
            'seasons.*.adult_price' => ['nullable', 'numeric', 'min:0'],
            'seasons.*.child_price' => ['nullable', 'numeric', 'min:0'],
            'seasons.*.partner_price' => ['nullable', 'numeric', 'min:0'],
            'seasons.*.price_per_night' => ['nullable', 'numeric', 'min:0'],
            'seasons.*.unit_prices' => ['nullable', 'array'],
            'seasons.*.unit_prices.*.price' => ['nullable', 'numeric', 'min:0'],
            'seasons.*.unit_prices.*.partner_price' => ['nullable', 'numeric', 'min:0'],
            'seasons.*.tier_prices' => ['nullable', 'array'],
            'seasons.*.tier_prices.*.price' => ['nullable', 'numeric', 'min:0'],
            'seasons.*.tier_prices.*.partner_price' => ['nullable', 'numeric', 'min:0'],
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
}
