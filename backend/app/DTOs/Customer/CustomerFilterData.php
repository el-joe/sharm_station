<?php

namespace App\DTOs\Customer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Customers list filters (A2).
 */
final readonly class CustomerFilterData
{
    public function __construct(
        public ?string $search = null,
        public ?string $nationalityCode = null,
        public ?bool $active = null,
        public ?bool $withBookings = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $bool = fn (string $key): ?bool => isset($data[$key]) && $data[$key] !== '' ? filter_var($data[$key], FILTER_VALIDATE_BOOLEAN) : null;

        return new self(
            search: filled($data['search'] ?? null) ? trim((string) $data['search']) : null,
            nationalityCode: filled($data['nationality_code'] ?? null) ? strtoupper((string) $data['nationality_code']) : null,
            active: $bool('active'),
            withBookings: $bool('with_bookings'),
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }
}
