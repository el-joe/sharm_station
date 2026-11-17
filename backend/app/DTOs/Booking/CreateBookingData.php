<?php

namespace App\DTOs\Booking;

use App\DTOs\Pricing\QuoteRequestData;
use App\DTOs\Pricing\RoomQuoteRequestData;
use App\DTOs\Transfer\TransferQuoteRequestData;
use App\Enums\ServiceType;
use App\Models\Partner;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One of: a trip booking ($tripSlug + $tripQuote), a package booking ($packageSlug + $tripQuote, per person),
 * an accommodation booking ($roomId + $roomQuote) or a transfer booking ($transferQuote).
 */
final readonly class CreateBookingData
{
    /**
     * @param  list<TravelerData>  $travelers
     */
    public function __construct(
        public string $leadName,
        public string $leadEmail,
        public ?string $leadPhone,
        public array $travelers,
        public ?string $tripSlug = null,
        public ?QuoteRequestData $tripQuote = null,
        public ?int $roomId = null,
        public ?RoomQuoteRequestData $roomQuote = null,
        public ?string $notes = null,
        public ?int $userId = null,
        public ?Partner $partner = null,
        public string $locale = 'en',
        public string $currency = 'EUR',
        public ?string $packageSlug = null,
        public ?TransferQuoteRequestData $transferQuote = null,
        public ?string $nationalityCode = null,
        public ?int $nationalityGroupId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?int $userId = null, ?Partner $partner = null, string $locale = 'en', string $currency = 'EUR', ?int $nationalityGroupId = null): self
    {
        $nationalityCode = isset($data['nationality_code']) ? strtoupper((string) $data['nationality_code']) : null;

        $service = self::serviceFor($data);
        $isRoom = $service === ServiceType::Accommodation;
        $isTransfer = $service === ServiceType::Transfer;
        $isPackage = $service === ServiceType::Package;

        return new self(
            leadName: $data['lead']['name'],
            leadEmail: $data['lead']['email'],
            leadPhone: $data['lead']['phone'] ?? null,
            travelers: array_values(array_map(TravelerData::fromArray(...), $data['travelers'] ?? [])),
            tripSlug: $isRoom || $isTransfer || $isPackage ? null : ($data['trip_slug'] ?? null),
            tripQuote: $isRoom || $isTransfer ? null : QuoteRequestData::fromArray($data, $partner, $currency, $nationalityCode, $nationalityGroupId),
            roomId: $isRoom ? (int) $data['room_id'] : null,
            roomQuote: $isRoom ? RoomQuoteRequestData::fromArray($data, $partner, $currency, $nationalityCode, $nationalityGroupId) : null,
            notes: $data['notes'] ?? null,
            userId: $userId,
            partner: $partner,
            locale: $locale,
            currency: $currency,
            packageSlug: $isPackage ? $data['package_slug'] : null,
            transferQuote: $isTransfer ? TransferQuoteRequestData::fromArray($data['transfer'], $partner, $currency, $nationalityCode, $nationalityGroupId) : null,
            nationalityCode: $nationalityCode,
            nationalityGroupId: $nationalityGroupId,
        );
    }

    public static function fromRequest(FormRequest $request, ?Partner $partner = null, string $currency = 'EUR', ?int $nationalityGroupId = null): self
    {
        return self::fromArray(
            $request->validated(),
            $request->user('sanctum')?->getKey(),
            $partner,
            app()->getLocale(),
            $currency,
            $nationalityGroupId,
        );
    }

    /**
     * Service of a booking payload: explicit "service" or inferred from the product key.
     *
     * @param  array<string, mixed>  $data
     */
    public static function serviceFor(array $data): ServiceType
    {
        return match (true) {
            isset($data['room_id']) => ServiceType::Accommodation,
            isset($data['package_slug']) || ($data['service'] ?? null) === ServiceType::Package->value => ServiceType::Package,
            isset($data['transfer']) || ($data['service'] ?? null) === ServiceType::Transfer->value => ServiceType::Transfer,
            default => ServiceType::Trip,
        };
    }

    public function isAccommodation(): bool
    {
        return $this->roomId !== null;
    }

    public function isPackage(): bool
    {
        return $this->packageSlug !== null;
    }

    public function isTransfer(): bool
    {
        return $this->transferQuote !== null;
    }
}
