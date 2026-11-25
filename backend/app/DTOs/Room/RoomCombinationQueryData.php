<?php

namespace App\DTOs\Room;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Best combinations" query: party and stay in one building (SRS 6.4).
 */
final readonly class RoomCombinationQueryData
{
    /**
     * @param  list<int>  $childAges
     */
    public function __construct(
        public string $building,
        public CarbonImmutable $checkIn,
        public CarbonImmutable $checkOut,
        public int $adults,
        public array $childAges = [],
        public int $limit = 10,
        public ?int $nationalityGroupId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?int $nationalityGroupId = null): self
    {
        return new self(
            building: (string) $data['building'],
            checkIn: CarbonImmutable::parse($data['check_in'])->startOfDay(),
            checkOut: CarbonImmutable::parse($data['check_out'])->startOfDay(),
            adults: (int) $data['adults'],
            childAges: array_values(array_map('intval', $data['child_ages'] ?? [])),
            limit: (int) ($data['limit'] ?? 10),
            nationalityGroupId: $nationalityGroupId,
        );
    }

    public static function fromRequest(FormRequest $request, ?int $nationalityGroupId = null): self
    {
        return self::fromArray($request->validated(), $nationalityGroupId);
    }
}
