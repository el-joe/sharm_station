<?php

namespace App\Services;

use App\DTOs\Booking\CreateBookingData;
use App\DTOs\Pricing\QuoteRequestData;
use App\DTOs\Pricing\QuoteResultData;
use App\DTOs\Pricing\RoomQuoteRequestData;
use App\DTOs\Transfer\TransferQuoteRequestData;
use App\Exceptions\DomainValidationException;
use App\Models\Booking;
use App\Models\Partner;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Repositories\Contracts\TripRepositoryInterface;
use App\Services\Pricing\PackagePricingEngine;
use App\Services\Pricing\RoomPricingEngine;
use App\Services\Pricing\TransferPricingService;
use App\Services\Pricing\TripPricingEngine;

/**
 * Partner panel booking flow (CR-13, CR-15): quotes and bookings priced for the end client's nationality,
 * at partner prices (customer price − commission %), with the partner recorded as the booker.
 */
class PartnerBookingService
{
    public function __construct(
        private readonly TripRepositoryInterface $trips,
        private readonly PackageRepositoryInterface $packages,
        private readonly RoomRepositoryInterface $rooms,
        private readonly TripPricingEngine $tripPricing,
        private readonly PackagePricingEngine $packagePricing,
        private readonly RoomPricingEngine $roomPricing,
        private readonly TransferPricingService $transferPricing,
        private readonly BookingService $bookings,
        private readonly NationalityService $nationalities,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated PartnerQuoteRequest payload
     *
     * @throws DomainValidationException when the product does not exist
     */
    public function quote(Partner $partner, array $data, string $currency): QuoteResultData
    {
        $code = strtoupper((string) $data['nationality_code']);
        $groupId = $this->nationalities->groupFor($code)->id;

        return match ($data['product_type']) {
            'trip' => $this->tripPricing->quote(
                $this->trips->findPublishedBySlug((string) $data['slug']) ?? throw $this->notFound('slug', 'This trip is not available.'),
                QuoteRequestData::fromArray($data, $partner, $currency, $code, $groupId),
            ),
            'package' => $this->packagePricing->quote(
                $this->packages->findPublishedBySlug((string) $data['slug']) ?? throw $this->notFound('slug', 'This package is not available.'),
                QuoteRequestData::fromArray($data, $partner, $currency, $code, $groupId),
            ),
            'room' => $this->roomQuote($partner, $data, $currency, $code, $groupId),
            'transfer' => $this->transferPricing->quote(TransferQuoteRequestData::fromArray($data['transfer'], $partner, $currency, $code, $groupId)),
        };
    }

    /**
     * @param  array<string, mixed>  $data  validated PartnerBookingRequest payload
     *
     * @throws DomainValidationException when the quote or the travellers are invalid
     */
    public function book(Partner $partner, array $data, string $currency, string $locale): Booking
    {
        $groupId = $this->nationalities->groupFor((string) $data['nationality_code'])->id;

        $payload = match ($data['product_type']) {
            'trip' => [...$data, 'trip_slug' => $data['slug']],
            'package' => [...$data, 'package_slug' => $data['slug']],
            'room' => isset($data['rooms']) ? [...$data, 'service' => 'accommodation'] : [...$data, 'room_id' => (int) $data['id']],
            'transfer' => $data,
        };

        unset($payload['slug'], $payload['id'], $payload['product_type']);

        return $this->bookings->createFromQuote(
            CreateBookingData::fromArray($payload, $partner->user_id, $partner, $locale, $currency, $groupId),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function paymentOptions(Booking $booking): array
    {
        return $this->bookings->paymentOptions($booking);
    }

    /**
     * One room (id) or several rooms of one building (rooms[]): accommodation has no commission.
     *
     * @param  array<string, mixed>  $data
     */
    private function roomQuote(Partner $partner, array $data, string $currency, string $code, int $groupId): QuoteResultData
    {
        $request = RoomQuoteRequestData::fromArray($data, $partner, $currency, $code, $groupId);
        $rooms = $this->rooms->findBookableMany($request->roomIds());

        if ($rooms->isEmpty()) {
            throw $this->notFound($request->multiRoom ? 'rooms' : 'id', 'This room is not available.');
        }

        return $this->roomPricing->quoteRooms($rooms, $request);
    }

    private function notFound(string $field, string $message): DomainValidationException
    {
        return DomainValidationException::withErrors([$field => [$message]]);
    }
}
