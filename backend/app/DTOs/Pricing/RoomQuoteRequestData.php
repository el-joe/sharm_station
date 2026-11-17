<?php

namespace App\DTOs\Pricing;

use App\Models\Partner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final readonly class RoomQuoteRequestData
{
    /**
     * @param  list<int>  $childAges
     */
    public function __construct(
        public CarbonImmutable $checkIn,
        public CarbonImmutable $checkOut,
        public int $adults,
        public int $children = 0,
        public array $childAges = [],
        public ?Partner $partner = null,
        public string $currency = 'EUR',
        public ?string $nationalityCode = null,
        public ?int $nationalityGroupId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?Partner $partner = null, string $currency = 'EUR', ?string $nationalityCode = null, ?int $nationalityGroupId = null): self
    {
        return new self(
            checkIn: CarbonImmutable::parse($data['check_in'])->startOfDay(),
            checkOut: CarbonImmutable::parse($data['check_out'])->startOfDay(),
            adults: (int) ($data['adults'] ?? 1),
            children: (int) ($data['children'] ?? 0),
            childAges: array_values(array_map('intval', $data['child_ages'] ?? [])),
            partner: $partner,
            currency: $currency,
            nationalityCode: $nationalityCode,
            nationalityGroupId: $nationalityGroupId,
        );
    }

    public static function fromRequest(FormRequest $request, ?Partner $partner = null, string $currency = 'EUR', ?string $nationalityCode = null, ?int $nationalityGroupId = null): self
    {
        return self::fromArray($request->validated(), $partner, $currency, $nationalityCode, $nationalityGroupId);
    }

    public function nights(): int
    {
        return (int) $this->checkIn->diffInDays($this->checkOut, false);
    }
}
