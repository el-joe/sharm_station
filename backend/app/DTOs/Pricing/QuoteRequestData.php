<?php

namespace App\DTOs\Pricing;

use App\Enums\BookingType;
use App\Models\Partner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final readonly class QuoteRequestData
{
    /**
     * @param  list<int>  $childAges
     * @param  list<UnitSelectionData>  $units
     * @param  list<AddonSelectionData>  $addons
     */
    public function __construct(
        public BookingType $bookingType,
        public CarbonImmutable $travelDate,
        public int $adults,
        public int $children = 0,
        public array $childAges = [],
        public array $units = [],
        public array $addons = [],
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
        $bookingType = $data['booking_type'] instanceof BookingType ? $data['booking_type'] : BookingType::from($data['booking_type']);

        return new self(
            bookingType: $bookingType,
            travelDate: CarbonImmutable::parse($data['travel_date'])->startOfDay(),
            adults: (int) ($data['adults'] ?? 1),
            children: (int) ($data['children'] ?? 0),
            childAges: array_values(array_map('intval', $data['child_ages'] ?? [])),
            units: array_values(array_map(UnitSelectionData::fromArray(...), $data['units'] ?? [])),
            addons: array_values(array_map(AddonSelectionData::fromArray(...), $data['addons'] ?? [])),
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

    public function persons(): int
    {
        return $this->adults + $this->children;
    }
}
