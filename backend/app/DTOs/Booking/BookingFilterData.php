<?php

namespace App\DTOs\Booking;

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Enums\PaymentStatus;
use App\Enums\ServiceType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin bookings list filters (SRS 12.3). Dates filter the service date (start date or check-in).
 */
final readonly class BookingFilterData
{
    public function __construct(
        public ?string $search = null,
        public ?ServiceType $serviceType = null,
        public ?BookingType $bookingType = null,
        public ?BookingStatus $status = null,
        public ?PaymentStatus $paymentStatus = null,
        public ?int $partnerId = null,
        public ?CarbonImmutable $dateFrom = null,
        public ?CarbonImmutable $dateTo = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $date = fn (string $key): ?CarbonImmutable => filled($data[$key] ?? null) ? CarbonImmutable::parse($data[$key])->startOfDay() : null;

        return new self(
            search: filled($data['search'] ?? null) ? trim((string) $data['search']) : null,
            serviceType: ServiceType::tryFrom((string) ($data['service_type'] ?? '')),
            bookingType: BookingType::tryFrom((string) ($data['booking_type'] ?? '')),
            status: BookingStatus::tryFrom((string) ($data['status'] ?? '')),
            paymentStatus: PaymentStatus::tryFrom((string) ($data['payment_status'] ?? '')),
            partnerId: filled($data['partner_id'] ?? null) ? (int) $data['partner_id'] : null,
            dateFrom: $date('date_from'),
            dateTo: $date('date_to'),
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }
}
