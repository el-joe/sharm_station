<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Partner panel quote (CR-15): product_type + slug (trip, package) or id (room), the end client's nationality
 * and the same configuration fields as the public quotes (transfer fields under "transfer.").
 */
class PartnerQuoteRequest extends FormRequest
{
    public const PRODUCT_TYPES = ['trip', 'package', 'room', 'transfer'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nationality_code'))) {
            $this->merge(['nationality_code' => strtoupper($this->input('nationality_code'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...self::productRules((string) $this->input('product_type'))];
    }

    /**
     * @return array<string, mixed>
     */
    public static function productRules(string $productType): array
    {
        $common = [
            'product_type' => ['required', Rule::in(self::PRODUCT_TYPES)],
            'slug' => [Rule::requiredIf(in_array($productType, ['trip', 'package'], true)), 'nullable', 'string', 'max:255'],
            'id' => [Rule::requiredIf($productType === 'room'), 'nullable', 'integer'],
            'nationality_code' => ['required', 'string', 'size:2', Rule::exists('countries', 'code')],
        ];

        $productRules = match ($productType) {
            'package' => PackageQuoteRequest::quoteRules(),
            'room' => RoomQuoteRequest::stayRules(),
            'transfer' => ['transfer' => ['required', 'array'], ...TransferQuoteRequest::quoteRules('transfer.')],
            'trip' => TripQuoteRequest::quoteRules(),
            default => [],
        };

        return [...$common, ...$productRules];
    }
}
