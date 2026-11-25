<?php

namespace App\Services;

use App\DTOs\Booking\BookingFilterData;
use App\DTOs\Booking\CreateBookingData;
use App\DTOs\Booking\TravelerData;
use App\DTOs\Coupon\CouponCheckData;
use App\DTOs\Pricing\QuoteLineData;
use App\DTOs\Pricing\QuoteResultData;
use App\Enums\ActivityAction;
use App\Enums\BookingStatus;
use App\Enums\NotificationEvent;
use App\Enums\PaymentStatus;
use App\Enums\PricingMode;
use App\Enums\RoomAvailabilityStatus;
use App\Enums\ServiceType;
use App\Enums\TransferStatus;
use App\Enums\TravelerType;
use App\Exceptions\DomainValidationException;
use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Models\Coupon;
use App\Models\PaymentMethod;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\TransferHub;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Contracts\PaymentMethodRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Repositories\Contracts\TripRepositoryInterface;
use App\Services\Notifications\NotificationContextFactory;
use App\Services\Notifications\NotificationService;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Pricing\PackagePricingEngine;
use App\Services\Pricing\RoomPricingEngine;
use App\Services\Pricing\TransferPricingService;
use App\Services\Pricing\TripPricingEngine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

/**
 * Creates bookings from a quote payload. Prices are always recomputed on the server.
 */
class BookingService
{
    public const REFERENCE_PREFIX = 'BK-';

    public const FIRST_REFERENCE_NUMBER = 2041;

    public const DEFAULT_HOLD_MINUTES = 30;

    public const DEFAULT_PAYMENT_DUE_HOURS = 48;

    public function __construct(
        private readonly BookingRepositoryInterface $bookings,
        private readonly TripRepositoryInterface $trips,
        private readonly RoomRepositoryInterface $rooms,
        private readonly TripPricingEngine $tripPricing,
        private readonly RoomPricingEngine $roomPricing,
        private readonly SettingRepositoryInterface $settings,
        private readonly PackageRepositoryInterface $packages,
        private readonly PackagePricingEngine $packagePricing,
        private readonly TransferPricingService $transferPricing,
        private readonly TransferService $transfers,
        private readonly PaymentMethodRepositoryInterface $paymentMethods,
        private readonly ActivityLogger $activity,
        private readonly CouponService $coupons,
        private readonly NotificationService $notifications,
        private readonly NotificationContextFactory $notificationContext,
        private readonly PaymentGatewayManager $gateways,
        private readonly BookingRoomWriter $roomWriter,
    ) {}

    /**
     * @throws DomainValidationException when the quote or the traveller list is invalid.
     */
    public function createFromQuote(CreateBookingData $data): Booking
    {
        $booking = match (true) {
            $data->isAccommodation() => $this->createAccommodationBooking($data),
            $data->isPackage() => $this->createPackageBooking($data),
            $data->isTransfer() => $this->createTransferBooking($data),
            default => $this->createTripBooking($data),
        };

        $booking->load(['travelers', 'units', 'addons', 'rooms.guests', 'bookable', 'includedTransfers', 'transferRequest', 'nationalityGroup']);

        if ($data->sendNotifications) {
            $this->notifyCreated($booking);
        }

        return $booking;
    }

    /**
     * SRS 15.1: new booking to the booker and the admin team, plus the transfer request or the included-transfer tasks to arrange.
     */
    private function notifyCreated(Booking $booking): void
    {
        $this->notifications->booking(NotificationEvent::BookingCreated, $booking);
        $this->notifications->booking(NotificationEvent::BookingCreatedAdmin, $booking);

        if ($booking->transferRequest !== null || $booking->includedTransfers->isNotEmpty()) {
            $booking->loadMissing(['transferRequest.hub', 'transferRequest.vehicleType']);
            $event = $booking->transferRequest !== null ? NotificationEvent::TransferRequestedAdmin : NotificationEvent::IncludedTransferAdmin;
            $this->notifications->booking($event, $booking, ['transfer_details' => $this->notificationContext->transferDetails($booking, 'en')]);
        }
    }

    public function findByReference(string $reference): ?Booking
    {
        return $this->bookings->findByReference($reference);
    }

    /**
     * Booking owner, staff, or anyone holding the booking's access token may view it.
     */
    public function canView(Booking $booking, ?User $user, ?string $token): bool
    {
        if ($token !== null && $token !== '' && hash_equals($booking->access_token, $token)) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        return $user->isStaff()
            || ($booking->user_id !== null && $booking->user_id === $user->id)
            || ($booking->partner_id !== null && $booking->partner_id === $user->partner?->id);
    }

    public function changeStatus(Booking $booking, BookingStatus $status, ?User $actor = null, ?string $note = null): Booking
    {
        return DB::transaction(function () use ($booking, $status, $actor, $note): Booking {
            $from = $booking->status;
            $this->bookings->update($booking, ['status' => $status]);

            $booking->statusLogs()->create([
                'from_status' => $from,
                'to_status' => $status,
                'user_id' => $actor?->id,
                'note' => $note,
            ]);

            if (in_array($status, [BookingStatus::Cancelled, BookingStatus::Refunded], true)) {
                RoomAvailability::query()->where('booking_id', $booking->id)->delete();
            }

            if ($actor?->isStaff() && $from !== $status) {
                $this->activity->log(ActivityAction::StatusChanged, $booking, "Booking {$booking->reference}: {$from?->label()} → {$status->label()}", array_filter([
                    'from' => $from?->value,
                    'to' => $status->value,
                    'note' => $note,
                ]), $actor);
            }

            return $booking;
        });
    }

    /**
     * Manual hold release from the admin (SRS 6.5, 12.3): an unpaid accommodation booking that still holds rooms is
     * cancelled now instead of at the end of the hold, which frees its nights. No e-mail is sent (staff decision).
     *
     * @throws DomainValidationException when the booking holds no room
     */
    public function releaseHold(Booking $booking, User $actor): Booking
    {
        $holds = RoomAvailability::query()->where('booking_id', $booking->id)->where('status', RoomAvailabilityStatus::Held)->count();

        if ($holds === 0 || $booking->status !== BookingStatus::AwaitingPayment) {
            throw DomainValidationException::withErrors(['booking' => ['This booking holds no room: only unpaid bookings with held nights can be released.']]);
        }

        $this->changeStatus($booking, BookingStatus::Cancelled, $actor, 'Hold released by the admin');
        $this->activity->log(ActivityAction::HoldReleased, $booking, "Hold of booking {$booking->reference} released ({$holds} ".str('night')->plural($holds).' freed)', ['nights' => $holds], $actor);

        return $booking;
    }

    /**
     * Confirmed and fully paid bookings become "Completed" the day after their service ended. Idempotent.
     */
    public function markCompleted(?CarbonImmutable $today = null): int
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $count = 0;

        foreach ($this->bookings->endedBefore($today) as $booking) {
            $this->changeStatus($booking, BookingStatus::Completed, null, 'Service ended on '.($booking->end_date ?? $booking->check_out ?? $booking->start_date)?->toDateString());
            $count++;
        }

        return $count;
    }

    /**
     * Balance due date chosen by the admin (null = the default: service date minus the settings days).
     */
    public function setBalanceDueOn(Booking $booking, ?string $date, User $actor): Booking
    {
        $this->bookings->update($booking, ['balance_due_on' => $date]);
        $this->activity->log(ActivityAction::Updated, $booking, "Balance due date of {$booking->reference} set to ".($date ?? 'default'), ['balance_due_on' => $date], $actor);

        return $booking;
    }

    /**
     * Payment methods enabled for the booking currency: the payment_methods table when it has rows,
     * otherwise the legacy settings "payment.methods" list.
     *
     * @return list<array<string, mixed>>
     */
    public function paymentOptions(Booking $booking): array
    {
        $configured = $this->paymentMethods->enabledForCurrency($booking->currency)
            ->filter(fn (PaymentMethod $method): bool => $method->isManual() || $this->gateways->isAvailable($method->driver));

        if ($configured->isNotEmpty() || $this->paymentMethods->query()->exists()) {
            return $configured->map(fn (PaymentMethod $method): array => [
                'id' => $method->id,
                'code' => $method->code,
                'type' => $method->type->value,
                'name' => (string) $method->name,
                'proof_required' => $method->proof_required,
            ])->values()->all();
        }

        $methods = $this->settings->get('payment.methods', []);

        return array_values(array_map(
            fn (array $method): array => array_diff_key($method, ['currencies' => true]),
            array_filter($methods, fn (array $method): bool => empty($method['currencies']) || in_array($booking->currency, $method['currencies'], true)),
        ));
    }

    /**
     * Next sequential reference, e.g. BK-2041. Must run inside the booking transaction.
     */
    public function nextReference(): string
    {
        $last = $this->bookings->lastReferenceNumber();

        return self::REFERENCE_PREFIX.max(($last ?? 0) + 1, self::FIRST_REFERENCE_NUMBER);
    }

    private function createTripBooking(CreateBookingData $data): Booking
    {
        $trip = $data->tripSlug !== null ? $this->trips->findPublishedBySlug($data->tripSlug) : null;

        if ($trip === null || $data->tripQuote === null) {
            throw DomainValidationException::withErrors(['trip_slug' => ['This trip is not available.']]);
        }

        $quote = $this->tripPricing->quote($trip, $data->tripQuote);
        $this->ensureBookable($quote, $data, $data->tripQuote->adults, $data->tripQuote->children);

        return DB::transaction(function () use ($data, $trip, $quote): Booking {
            $booking = $this->createBookingRecord($data, $quote, [
                'service_type' => $trip->type->serviceType(),
                'booking_type' => $data->tripQuote->bookingType,
                'pricing_mode' => $trip->pricing_mode,
                'bookable_type' => $trip->getMorphClass(),
                'bookable_id' => $trip->id,
                'start_date' => $data->tripQuote->travelDate->toDateString(),
                'end_date' => $data->tripQuote->travelDate->addDays(max(1, (int) $trip->days) - 1)->toDateString(),
                'adults' => $data->tripQuote->adults,
                'children' => $data->tripQuote->children,
            ]);

            $this->storeUnits($booking, $quote);
            $this->storeAddons($booking, $quote);
            $this->roomWriter->storeRoomOptions($booking, $quote, $this->storeTravelers($booking, $data->travelers));

            return $booking;
        });
    }

    /**
     * Package booking (SRS 4.2): per person, no inventory; one included-transfer task per template, without price.
     */
    private function createPackageBooking(CreateBookingData $data): Booking
    {
        $package = $this->packages->findPublishedBySlug((string) $data->packageSlug);

        if ($package === null || $data->tripQuote === null) {
            throw DomainValidationException::withErrors(['package_slug' => ['This package is not available.']]);
        }

        $quote = $this->packagePricing->quote($package, $data->tripQuote);
        $this->ensureBookable($quote, $data, $data->tripQuote->adults, $data->tripQuote->children);

        return DB::transaction(function () use ($data, $package, $quote): Booking {
            $booking = $this->createBookingRecord($data, $quote, [
                'service_type' => ServiceType::Package,
                'booking_type' => $data->tripQuote->bookingType,
                'pricing_mode' => PricingMode::PerPerson,
                'bookable_type' => $package->getMorphClass(),
                'bookable_id' => $package->id,
                'start_date' => $data->tripQuote->travelDate->toDateString(),
                'end_date' => $data->tripQuote->travelDate->addDays(max(1, (int) $package->days) - 1)->toDateString(),
                'adults' => $data->tripQuote->adults,
                'children' => $data->tripQuote->children,
            ]);

            $this->storeAddons($booking, $quote);
            $this->roomWriter->storeRoomOptions($booking, $quote, $this->storeTravelers($booking, $data->travelers));

            foreach ($package->includedTransfers as $template) {
                $booking->includedTransfers()->create([
                    'package_included_transfer_id' => $template->id,
                    'from_place' => $template->from_place,
                    'to_place' => $template->to_place,
                    'date' => $data->tripQuote->travelDate->addDays(max(1, $template->day_number) - 1)->toDateString(),
                    'time' => $template->time,
                    'vehicle_type' => $template->vehicle_type,
                    'passengers' => $data->tripQuote->persons(),
                    'status' => TransferStatus::Pending,
                ]);
            }

            return $booking;
        });
    }

    /**
     * Standalone transfer (SRS 5): priced by the distance formula and stored with its breakdown as a transfer request.
     */
    private function createTransferBooking(CreateBookingData $data): Booking
    {
        $request = $data->transferQuote;
        $quote = $this->transferPricing->quote($request);

        $errors = $quote->errors;

        if ($data->travelers !== [] && count($data->travelers) > $request->passengers) {
            $errors['travelers'][] = "Enter at most {$request->passengers} passengers.";
        }

        if ($errors !== []) {
            throw DomainValidationException::withErrors($errors);
        }

        return DB::transaction(function () use ($data, $request, $quote): Booking {
            $booking = $this->createBookingRecord($data, $quote, [
                'service_type' => ServiceType::Transfer,
                'bookable_type' => (new TransferHub)->getMorphClass(),
                'bookable_id' => $request->hubId,
                'start_date' => $request->pickupAt->toDateString(),
                'end_date' => ($request->returnAt ?? $request->pickupAt)->toDateString(),
                'adults' => $request->passengers,
                'children' => 0,
            ]);

            $this->storeTravelers($booking, $data->travelers);
            $this->transfers->createRequest($booking, $request, $quote);

            return $booking;
        });
    }

    /**
     * Accommodation booking with one or more rooms of the same building (SRS 6.4, 6.5): the rooms are locked,
     * priced and validated (allocation rules) inside the transaction, then their nights are held until the
     * payment deadline.
     */
    private function createAccommodationBooking(CreateBookingData $data): Booking
    {
        return DB::transaction(function () use ($data): Booking {
            $request = $data->roomQuote;
            $rooms = $request !== null ? $this->rooms->lockManyForBooking($request->roomIds())->keyBy('id') : collect();

            if ($request === null || $rooms->isEmpty()) {
                throw DomainValidationException::withErrors([($request?->multiRoom ? 'rooms' : 'room_id') => ['This room is not available.']]);
            }

            $quote = $this->roomPricing->quoteRooms($rooms, $request);
            $this->ensureBookable($quote, $data, $request->adults, $request->children);
            $first = $rooms->get($request->rooms[0]->roomId) ?? $rooms->first();

            $booking = $this->createBookingRecord($data, $quote, [
                'service_type' => ServiceType::Accommodation,
                'bookable_type' => $first->getMorphClass(),
                'bookable_id' => $first->id,
                'check_in' => $request->checkIn->toDateString(),
                'check_out' => $request->checkOut->toDateString(),
                'start_date' => $request->checkIn->toDateString(),
                'end_date' => $request->checkOut->toDateString(),
                'adults' => $request->adults,
                'children' => $request->children,
            ]);

            $this->roomWriter->storeStayRooms($booking, $rooms, $quote, $this->storeTravelers($booking, $data->travelers));
            $this->roomWriter->blockNights($booking, $quote, RoomAvailabilityStatus::Held, $booking->payment_due_at);

            return $booking;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createBookingRecord(CreateBookingData $data, QuoteResultData $quote, array $attributes): Booking
    {
        [$quote, $coupon] = $this->applyCoupon($data, $quote, $attributes['service_type']);

        $booking = $this->bookings->create([
            ...$attributes,
            'reference' => $this->nextReference(),
            'access_token' => Str::random(48),
            'season_name' => $quote->seasonName,
            'user_id' => $data->userId,
            'partner_id' => $data->partner?->isActive() ? $data->partner->id : null,
            'lead_name' => $data->leadName,
            'lead_email' => $data->leadEmail,
            'lead_phone' => $data->leadPhone,
            'retail_total' => $quote->retailTotal,
            'partner_total' => $quote->partnerTotal,
            'commission_amount' => $quote->commissionAmount,
            'commission_percent' => $quote->commissionPercent,
            'price_rule' => $quote->priceRule,
            'nationality_code' => $data->nationalityCode,
            'nationality_group_id' => $quote->nationality['group']['id'] ?? $data->nationalityGroupId,
            'total' => $quote->total,
            'deposit_percent' => $quote->depositPercent,
            'deposit_amount' => $quote->depositAmount,
            'balance_amount' => $quote->balanceAmount,
            'currency' => $quote->currency,
            'exchange_rate' => $quote->exchangeRate,
            'total_in_currency' => $quote->converted['total'],
            'status' => BookingStatus::AwaitingPayment,
            'payment_status' => PaymentStatus::AwaitingPayment,
            'payment_due_at' => $attributes['service_type'] === ServiceType::Accommodation
                ? now()->addMinutes((int) $this->settings->get('accommodation.hold_minutes', self::DEFAULT_HOLD_MINUTES))
                : now()->addHours((int) $this->settings->get('booking.payment_due_hours', self::DEFAULT_PAYMENT_DUE_HOURS)),
            'locale' => $data->locale,
            'notes' => $data->notes,
            'price_lines' => array_map(fn (QuoteLineData $line): array => $line->toArray($quote->isPartnerQuote()), $quote->lines),
            'coupon_id' => $coupon?->id,
            'coupon_code' => $coupon?->code,
            'discount_amount' => $coupon !== null ? -array_sum(array_map(fn (QuoteLineData $line): float => $line->total, $quote->linesOfType(QuoteLineData::TYPE_DISCOUNT))) : 0,
        ]);

        if ($coupon !== null) {
            $this->coupons->redeem($coupon, $booking, (float) $booking->discount_amount);
        }

        $booking->statusLogs()->create([
            'from_status' => null,
            'to_status' => BookingStatus::AwaitingPayment,
            'user_id' => $data->userId,
            'note' => 'Booking created',
        ]);

        return $booking;
    }

    /**
     * Applies the coupon code (validated again on the server, with the coupon row locked) as a discount line.
     *
     * @return array{0: QuoteResultData, 1: Coupon|null}
     *
     * @throws DomainValidationException when the code cannot be used on this booking.
     */
    private function applyCoupon(CreateBookingData $data, QuoteResultData $quote, ServiceType $service): array
    {
        if ($data->couponCode === null) {
            return [$quote, null];
        }

        $result = $this->coupons->check(new CouponCheckData(
            code: $data->couponCode,
            serviceType: $service,
            totalEur: $quote->total,
            userId: $data->partner === null ? $data->userId : null,
            email: $data->leadEmail,
            partner: $data->partner?->isActive() ? $data->partner : null,
        ), lock: true);

        if (! $result->valid || $result->coupon === null) {
            throw DomainValidationException::withErrors(['coupon_code' => [$result->message]]);
        }

        return [$quote->withDiscount($result->discountEur, 'Coupon '.$result->coupon->code, ['coupon_code' => $result->coupon->code]), $result->coupon];
    }

    /**
     * Per-unit lines of a trip quote as booking units.
     */
    public function storeUnits(Booking $booking, QuoteResultData $quote): void
    {
        foreach ($quote->linesOfType(QuoteLineData::TYPE_UNIT) as $line) {
            $booking->units()->create([
                'trip_unit_option_id' => $line->meta['unit_option_id'],
                'name' => $line->label,
                'seats' => $line->meta['seats'],
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice,
                'total' => $line->total,
                'partner_unit_price' => $line->partnerUnitPrice,
                'partner_total' => $line->partnerTotal,
            ]);
        }
    }

    public function storeAddons(Booking $booking, QuoteResultData $quote): void
    {
        foreach ($quote->linesOfType(QuoteLineData::TYPE_ADDON) as $line) {
            $booking->addons()->create([
                'addon_id' => $line->meta['addon_id'],
                'name' => $line->label,
                'pricing_basis' => $line->meta['pricing_basis'],
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice,
                'total' => $line->total,
                'partner_total' => $line->partnerTotal,
            ]);
        }
    }

    /**
     * @param  list<TravelerData>  $travelers
     * @return list<BookingTraveler>
     */
    private function storeTravelers(Booking $booking, array $travelers): array
    {
        $stored = [];

        foreach ($travelers as $index => $traveler) {
            $stored[] = $booking->travelers()->create([
                'first_name' => $traveler->firstName,
                'last_name' => $traveler->lastName,
                'type' => $traveler->type,
                'age' => $traveler->age,
                'nationality' => $traveler->nationality,
                'passport_number' => $traveler->passportNumber,
                'sort' => $index,
            ]);
        }

        return $stored;
    }

    private function ensureBookable(QuoteResultData $quote, CreateBookingData $data, int $adults, int $children): void
    {
        $errors = $quote->errors;
        $persons = $adults + $children;

        if (count($data->travelers) !== $persons) {
            $errors['travelers'][] = "Enter the details of all {$persons} travellers.";
        } else {
            $adultTravelers = count(array_filter($data->travelers, fn (TravelerData $traveler): bool => $traveler->type === TravelerType::Adult));

            if ($adultTravelers !== $adults) {
                $errors['travelers'][] = "The travellers must include {$adults} adults and {$children} children.";
            }
        }

        if ($errors !== []) {
            throw DomainValidationException::withErrors($errors);
        }
    }

    /**
     * Admin list query (DataTables, CSV export).
     *
     * @return Builder<Booking>
     */
    public function adminQuery(BookingFilterData $filters): Builder
    {
        return $this->bookings->adminQuery($filters);
    }

    /**
     * Booking with everything the admin detail page shows.
     */
    public function loadForAdmin(Booking $booking): Booking
    {
        return $booking->load([
            'travelers', 'units', 'addons', 'rooms.guests', 'bookable', 'partner.user', 'user', 'nationalityGroup',
            'statusLogs.user:id,name', 'includedTransfers', 'transferRequest.hub', 'transferRequest.vehicleType',
            'payments.method', 'payments.recorder:id,name', 'proofs.method', 'proofs.reviewer:id,name', 'cancellationRequester:id,name,email',
        ]);
    }

    /**
     * CSV rows for the filtered list, streamed with a cursor.
     *
     * @return LazyCollection<int, list<string|float|int|null>>
     */
    public function exportRows(BookingFilterData $filters): LazyCollection
    {
        return $this->bookings->adminQuery($filters)
            ->reorder()
            ->latest('id')
            ->cursor()
            ->map(fn (Booking $booking): array => [
                $booking->reference,
                $booking->created_at?->toDateTimeString(),
                $booking->service_type->label(),
                $booking->booking_type?->label(),
                ($booking->start_date ?? $booking->check_in)?->toDateString(),
                $booking->check_out?->toDateString(),
                $booking->lead_name,
                $booking->lead_email,
                $booking->lead_phone,
                $booking->partner?->company,
                $booking->adults,
                $booking->children,
                $booking->season_name,
                (float) $booking->retail_total,
                $booking->partner_total !== null ? (float) $booking->partner_total : null,
                (float) $booking->commission_amount,
                (float) $booking->total,
                (float) $booking->deposit_amount,
                $booking->paidAmount(),
                $booking->dueAmount(),
                $booking->currency,
                $booking->status->label(),
                $booking->payment_status->label(),
            ]);
    }

    /**
     * @return list<string>
     */
    public function exportHeadings(): array
    {
        return ['Reference', 'Created', 'Service', 'Booking type', 'Date', 'Check-out', 'Lead name', 'Email', 'Phone', 'Partner', 'Adults',
            'Children', 'Season', 'Retail total', 'Partner total', 'Commission', 'Total EUR', 'Deposit', 'Paid', 'Balance', 'Currency', 'Status', 'Payment status'];
    }
}
