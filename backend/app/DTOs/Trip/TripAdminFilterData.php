<?php

namespace App\DTOs\Trip;

use App\Enums\PricingMode;
use App\Enums\PublishStatus;
use App\Enums\TripType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin trips list filters. $nile switches between "Trips" (single/multi-day) and "Nile trips".
 */
final readonly class TripAdminFilterData
{
    public function __construct(
        public bool $nile = false,
        public ?TripType $type = null,
        public ?int $destinationId = null,
        public ?PublishStatus $status = null,
        public ?PricingMode $pricingMode = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            nile: ($data['scope'] ?? null) === 'nile',
            type: TripType::tryFrom((string) ($data['type'] ?? '')),
            destinationId: filled($data['destination_id'] ?? null) ? (int) $data['destination_id'] : null,
            status: PublishStatus::tryFrom((string) ($data['status'] ?? '')),
            pricingMode: PricingMode::tryFrom((string) ($data['pricing_mode'] ?? '')),
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }
}
