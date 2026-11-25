<?php

namespace App\DTOs\Booking;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Booking created from the admin (SRS 9.4, 12.3): for an existing or new customer, or on behalf of a partner,
 * priced by the same engines as the website. $quotePayload holds the product and quote fields in the public
 * booking API format (rooms[] + dates, or trip_slug/package_slug + travel_date, party, units, add-ons, room_options).
 */
final readonly class AdminBookingData
{
    public const PRODUCTS = ['room', 'trip', 'package'];

    /**
     * @param  array<string, mixed>  $quotePayload
     * @param  array{name: string, email: string, phone: string|null}|null  $newCustomer
     * @param  list<array<string, mixed>>  $travelers
     */
    public function __construct(
        public string $productType,
        public array $quotePayload,
        public string $nationalityCode,
        public ?int $customerId = null,
        public ?array $newCustomer = null,
        public ?int $partnerId = null,
        public ?string $leadName = null,
        public ?string $leadEmail = null,
        public ?string $leadPhone = null,
        public array $travelers = [],
        public ?string $notes = null,
        public bool $notify = true,
        public string $locale = 'en',
        public string $currency = 'EUR',
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated AdminBookingRequest payload
     */
    public static function fromArray(array $data): self
    {
        $product = (string) $data['product_type'];
        $party = [
            'adults' => (int) ($data['adults'] ?? 1),
            'children' => count($data['child_ages'] ?? []),
            'child_ages' => array_values(array_map('intval', $data['child_ages'] ?? [])),
        ];

        $quote = match ($product) {
            'room' => [
                'check_in' => $data['check_in'],
                'check_out' => $data['check_out'],
                'rooms' => array_values($data['rooms'] ?? []),
            ],
            default => [
                ($product === 'package' ? 'package_slug' : 'trip_slug') => $data['slug'],
                'booking_type' => $data['booking_type'],
                'travel_date' => $data['travel_date'],
                ...$party,
                'units' => array_values($data['units'] ?? []),
                'addons' => array_values($data['addons'] ?? []),
                'room_options' => array_values($data['room_options'] ?? []),
            ],
        };

        $booker = $data['booker'] ?? 'customer';

        return new self(
            productType: $product,
            quotePayload: $quote,
            nationalityCode: strtoupper((string) $data['nationality_code']),
            customerId: $booker === 'customer' && filled($data['customer_id'] ?? null) ? (int) $data['customer_id'] : null,
            newCustomer: $booker === 'new_customer' ? [
                'name' => (string) $data['new_customer']['name'],
                'email' => strtolower(trim((string) $data['new_customer']['email'])),
                'phone' => $data['new_customer']['phone'] ?? null,
            ] : null,
            partnerId: $booker === 'partner' ? (int) $data['partner_id'] : null,
            leadName: $data['lead']['name'] ?? null,
            leadEmail: $data['lead']['email'] ?? null,
            leadPhone: $data['lead']['phone'] ?? null,
            travelers: array_values($data['travelers'] ?? []),
            notes: $data['notes'] ?? null,
            notify: (bool) ($data['notify'] ?? true),
            locale: (string) ($data['locale'] ?? 'en'),
            currency: strtoupper((string) ($data['currency'] ?? 'EUR')),
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * Guests of the party: adults and children ages.
     *
     * @return array{adults: int, child_ages: list<int>}
     */
    public function party(): array
    {
        if ($this->productType !== 'room') {
            return ['adults' => (int) $this->quotePayload['adults'], 'child_ages' => $this->quotePayload['child_ages']];
        }

        $adults = 0;
        $ages = [];

        foreach ($this->quotePayload['rooms'] as $room) {
            $adults += (int) ($room['adults'] ?? 0);
            array_push($ages, ...array_map('intval', $room['children_ages'] ?? []));
        }

        return ['adults' => $adults, 'child_ages' => $ages];
    }
}
