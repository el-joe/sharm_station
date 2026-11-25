<?php

namespace App\Services;

use App\DTOs\Booking\AdminBookingData;
use App\DTOs\Booking\CreateBookingData;
use App\DTOs\Customer\CustomerFilterData;
use App\DTOs\Pricing\QuoteRequestData;
use App\DTOs\Pricing\QuoteResultData;
use App\DTOs\Pricing\RoomQuoteRequestData;
use App\Enums\ActivityAction;
use App\Enums\BookingType;
use App\Enums\PartnerStatus;
use App\Enums\PricingMode;
use App\Enums\TravelerType;
use App\Enums\UserRole;
use App\Exceptions\DomainValidationException;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Partner;
use App\Models\Room;
use App\Models\RoomOption;
use App\Models\Trip;
use App\Models\User;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Repositories\Contracts\TripRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Pricing\PackagePricingEngine;
use App\Services\Pricing\RoomPricingEngine;
use App\Services\Pricing\TripPricingEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bookings created by staff (SRS 9.4, 12.3): for an existing customer, a new customer, or on behalf of a partner.
 * Prices always come from the website engines (nationality group, season, partner commission); the booking is then
 * created by BookingService exactly like a website or partner-panel booking (holds, notifications, coupons).
 */
class AdminBookingService
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly RoomRepositoryInterface $rooms,
        private readonly TripRepositoryInterface $trips,
        private readonly PackageRepositoryInterface $packages,
        private readonly PartnerRepositoryInterface $partners,
        private readonly UserRepositoryInterface $users,
        private readonly RoomPricingEngine $roomPricing,
        private readonly TripPricingEngine $tripPricing,
        private readonly PackagePricingEngine $packagePricing,
        private readonly NationalityService $nationalities,
        private readonly ActivityLogger $activity,
        private readonly PasswordResetService $passwords,
    ) {}

    /**
     * Price of the booking as it would be created (partner prices when booked on behalf of a partner).
     *
     * @throws DomainValidationException when the product or the partner is not available
     */
    public function quote(AdminBookingData $data): QuoteResultData
    {
        $partner = $this->partner($data);
        $groupId = $this->nationalities->groupFor($data->nationalityCode)->id;
        $payload = $data->quotePayload;

        return match ($data->productType) {
            'room' => $this->roomQuote($payload, $partner, $data, $groupId),
            'package' => $this->packagePricing->quote(
                $this->packages->findPublishedBySlug((string) $payload['package_slug']) ?? throw DomainValidationException::withErrors(['slug' => ['This package is not available.']]),
                QuoteRequestData::fromArray($payload, $partner, $data->currency, $data->nationalityCode, $groupId),
            ),
            default => $this->tripPricing->quote(
                $this->trips->findPublishedBySlug((string) $payload['trip_slug']) ?? throw DomainValidationException::withErrors(['slug' => ['This trip is not available.']]),
                QuoteRequestData::fromArray($payload, $partner, $data->currency, $data->nationalityCode, $groupId),
            ),
        };
    }

    /**
     * @throws DomainValidationException
     */
    public function create(AdminBookingData $data, User $actor): Booking
    {
        $partner = $this->partner($data);
        $groupId = $this->nationalities->groupFor($data->nationalityCode)->id;

        $newCustomer = null;

        $booking = DB::transaction(function () use ($data, $partner, $groupId, &$newCustomer): Booking {
            [$customer, $created] = $partner === null ? $this->customer($data) : [null, false];
            $newCustomer = $created ? $customer : null;
            $lead = [
                'name' => $data->leadName ?? $customer?->name ?? $partner?->user?->name,
                'email' => $data->leadEmail ?? $customer?->email ?? $partner?->user?->email,
                'phone' => $data->leadPhone ?? $customer?->phone,
            ];

            if (blank($lead['name']) || blank($lead['email'])) {
                throw DomainValidationException::withErrors(['lead.name' => ['Enter the lead traveller name and email.']]);
            }

            $payload = [
                ...$data->quotePayload,
                'nationality_code' => $data->nationalityCode,
                'lead' => $lead,
                'travelers' => $data->travelers !== [] ? $data->travelers : $this->placeholderTravelers($data, (string) $lead['name']),
                'notes' => $data->notes,
            ];

            return $this->bookings->createFromQuote(CreateBookingData::fromArray(
                $payload,
                $partner?->user_id ?? $customer?->id,
                $partner,
                $data->locale,
                $data->currency,
                $groupId,
                $data->notify,
            ));
        });

        $this->activity->log(ActivityAction::BookingCreated, $booking, "Booking {$booking->reference} created from the admin".($partner !== null ? " on behalf of {$partner->company}" : " for {$booking->lead_name}"), [
            'partner_id' => $partner?->id,
            'user_id' => $booking->user_id,
            'total' => (float) $booking->total,
        ], $actor);

        if ($newCustomer !== null) {
            $this->passwords->sendWelcome($newCustomer);
            $this->activity->log(ActivityAction::Created, $newCustomer, "Customer account {$newCustomer->email} created with booking {$booking->reference}; welcome e-mail with the set-password link sent", [
                'booking_id' => $booking->id,
            ], $actor);
        }

        return $booking;
    }

    /**
     * Select options of the admin booking form: published trips and packages by slug, buildings, partners, countries.
     *
     * @return array{trips: array<string, string>, packages: array<string, string>, partners: array<int, string>, countries: array<string, string>}
     */
    public function formOptions(): array
    {
        return [
            'trips' => $this->trips->query()->published()->orderBy('title->en')->get(['id', 'slug', 'title', 'type'])
                ->mapWithKeys(fn (Trip $trip): array => [$trip->slug => $trip->title.' ('.$trip->type->label().')'])->all(),
            'packages' => $this->packages->query()->published()->orderBy('title->en')->get(['id', 'slug', 'title'])
                ->mapWithKeys(fn (Package $package): array => [$package->slug => (string) $package->title])->all(),
            'partners' => $this->partners->query()->where('status', PartnerStatus::Active)->orderBy('company')->pluck('company', 'id')->all(),
            'countries' => collect($this->nationalities->countryList())->mapWithKeys(fn (array $country): array => [$country['code'] => $country['name'].' ('.$country['group']['name'].')'])->all(),
        ];
    }

    /**
     * What the form needs to configure a product: booking types, unit options, add-ons and optional rooms of a trip
     * or package, or the bookable rooms of a building.
     *
     * @return array<string, mixed>
     */
    public function productOptions(string $type, string $key): array
    {
        if ($type === 'building') {
            return ['rooms' => $this->rooms->query()->bookable()->where('building_id', (int) $key)->orderBy('sort')->orderBy('id')->get()
                ->map(fn (Room $room): array => [
                    'id' => $room->id,
                    'name' => (string) $room->name.($room->number ? ' #'.$room->number : ''),
                    'room_type' => $room->room_type->label(),
                    'max_adults' => $room->max_adults,
                    'max_children' => $room->max_children,
                    'max_total' => $room->max_total,
                    'max_extra_beds' => $room->max_extra_beds,
                    'max_cots' => $room->max_cots,
                ])->all()];
        }

        $product = $type === 'package' ? $this->packages->findPublishedBySlug($key) : $this->trips->findPublishedBySlug($key);

        if ($product === null) {
            throw DomainValidationException::withErrors(['slug' => ['This product is not available.']]);
        }

        return [
            'booking_types' => collect(BookingType::cases())->filter(fn (BookingType $bookingType): bool => $product->supportsBookingType($bookingType))
                ->mapWithKeys(fn (BookingType $bookingType): array => [$bookingType->value => $bookingType->label()])->all(),
            'per_unit' => $product instanceof Trip && $product->pricing_mode === PricingMode::PerUnit,
            'unit_options' => $product instanceof Trip ? $product->unitOptions->map(fn ($option): array => ['id' => $option->id, 'name' => (string) $option->name, 'seats' => $option->seats])->values()->all() : [],
            'addons' => $product->addons->where('is_active', true)->map(fn ($addon): array => ['id' => $addon->id, 'name' => (string) $addon->name, 'required' => (bool) $addon->required, 'pricing_basis' => $addon->pricing_basis->value, 'max_quantity' => $addon->max_quantity])->values()->all(),
            'min_persons' => $product->min_persons,
            'max_persons' => $product->max_persons,
            'room_options' => $product->roomOptions->map(fn (RoomOption $option): array => [
                'id' => $option->id,
                'name' => $option->label().($option->stage_position ? ' · stage '.$option->stage_position : ''),
                'max_adults' => $option->max_adults,
                'max_children' => $option->max_children,
                'max_total' => $option->max_total,
            ])->values()->all(),
        ];
    }

    /**
     * Customers matching a search (name, e-mail or phone) for the booker picker.
     *
     * @return list<array{id: int, text: string, name: string, email: string, phone: string|null}>
     */
    public function searchCustomers(string $term): array
    {
        return $this->users->customersQuery(new CustomerFilterData(search: $term))
            ->reorder()
            ->orderBy('users.name')
            ->limit(20)
            ->get()
            ->map(fn (User $user): array => ['id' => $user->id, 'text' => $user->name.' · '.$user->email, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function roomQuote(array $payload, ?Partner $partner, AdminBookingData $data, int $groupId): QuoteResultData
    {
        $request = RoomQuoteRequestData::fromArray($payload, $partner, $data->currency, $data->nationalityCode, $groupId);
        $rooms = $this->rooms->findBookableMany($request->roomIds());

        if ($rooms->isEmpty()) {
            throw DomainValidationException::withErrors(['rooms' => ['Choose at least one available room.']]);
        }

        return $this->roomPricing->quoteRooms($rooms, $request);
    }

    /**
     * @throws DomainValidationException
     */
    private function partner(AdminBookingData $data): ?Partner
    {
        if ($data->partnerId === null) {
            return null;
        }

        /** @var Partner|null $partner */
        $partner = $this->partners->find($data->partnerId);

        if ($partner === null || ! $partner->isActive()) {
            throw DomainValidationException::withErrors(['partner_id' => ['Only active partners can book: approve the partner first.']]);
        }

        return $partner->loadMissing('user');
    }

    /**
     * Existing customer, or a new customer account (random password; create() then e-mails the "Welcome, set your
     * password" link). A new customer whose e-mail already exists is linked to that account.
     *
     * @return array{0: User|null, 1: bool} the customer and whether the account was created now
     */
    private function customer(AdminBookingData $data): array
    {
        if ($data->customerId !== null) {
            /** @var User|null $user */
            $user = $this->users->find($data->customerId);

            return $user?->role === UserRole::Customer ? [$user, false] : throw DomainValidationException::withErrors(['customer_id' => ['Choose a customer.']]);
        }

        if ($data->newCustomer === null) {
            return [null, false];
        }

        $existing = $this->users->findByEmail($data->newCustomer['email']);

        if ($existing !== null) {
            return $existing->role === UserRole::Customer ? [$existing, false] : throw DomainValidationException::withErrors(['new_customer.email' => ['This e-mail belongs to a staff or partner account.']]);
        }

        /** @var User $user */
        $user = $this->users->create([
            'name' => $data->newCustomer['name'],
            'email' => $data->newCustomer['email'],
            'phone' => $data->newCustomer['phone'],
            'password' => Str::password(32),
            'role' => UserRole::Customer,
            'is_active' => true,
            'locale' => $data->locale,
            'nationality_code' => $data->nationalityCode,
        ]);

        return [$user, true];
    }

    /**
     * Travellers named after the lead when staff do not enter them (editable later on the booking).
     *
     * @return list<array{first_name: string, last_name: string, type: string, age: int|null}>
     */
    private function placeholderTravelers(AdminBookingData $data, string $leadName): array
    {
        $party = $data->party();
        [$first, $last] = array_pad(explode(' ', trim($leadName), 2), 2, '-');
        $travelers = [];

        for ($adult = 1; $adult <= $party['adults']; $adult++) {
            $travelers[] = $adult === 1
                ? ['first_name' => $first, 'last_name' => $last, 'type' => TravelerType::Adult->value, 'age' => null]
                : ['first_name' => 'Adult '.$adult, 'last_name' => $last, 'type' => TravelerType::Adult->value, 'age' => null];
        }

        foreach ($party['child_ages'] as $index => $age) {
            $travelers[] = ['first_name' => 'Child '.($index + 1), 'last_name' => $last, 'type' => TravelerType::Child->value, 'age' => $age];
        }

        return $travelers;
    }
}
