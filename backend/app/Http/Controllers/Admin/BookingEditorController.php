<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\Booking\AdminBookingData;
use App\DTOs\Booking\BookingChangeData;
use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminBookingRequest;
use App\Http\Requests\Admin\BookingChangeRequest;
use App\Models\Booking;
use App\Services\AdminBookingService;
use App\Services\BookingModificationService;
use App\Services\BookingService;
use App\Services\BuildingService;
use App\Services\CurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin booking creation (for a customer or on behalf of a partner) and changes of dates, rooms, party, units and add-ons with a diff
 * shown before saving (SRS 9.4, 12.3). The pages are driven by the booking-editor JS module (JSON endpoints).
 */
class BookingEditorController extends Controller
{
    public function __construct(
        private readonly AdminBookingService $admin,
        private readonly BookingModificationService $modifications,
        private readonly BookingService $bookings,
    ) {}

    public function create(BuildingService $buildings, CurrencyService $currencies): View
    {
        return view('admin.bookings.create', [
            'options' => $this->admin->formOptions(),
            'buildings' => $buildings->options(),
            'currencies' => $currencies->enabled()->mapWithKeys(fn ($currency): array => [$currency->code => $currency->code])->all(),
        ]);
    }

    public function productOptions(Request $request): JsonResponse
    {
        $type = (string) $request->query('type');
        abort_unless(in_array($type, ['trip', 'package', 'building'], true) && filled($request->query('key')), 404);

        return response()->json(['data' => $this->admin->productOptions($type, (string) $request->query('key'))]);
    }

    public function customers(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));

        return response()->json(['results' => mb_strlen($term) < 2 ? [] : $this->admin->searchCustomers($term)]);
    }

    public function quote(AdminBookingRequest $request): JsonResponse
    {
        $quote = $this->admin->quote(AdminBookingData::fromRequest($request));

        return response()->json(['data' => $quote->toArray($quote->isPartnerQuote())], $quote->isValid() ? 200 : 422);
    }

    public function store(AdminBookingRequest $request): JsonResponse
    {
        $booking = $this->admin->create(AdminBookingData::fromRequest($request), $request->user());

        return response()->json([
            'message' => "Booking {$booking->reference} created.",
            'reference' => $booking->reference,
            'redirect' => route('admin.bookings.show', $booking),
        ], 201);
    }

    public function edit(Booking $booking): View
    {
        $booking->load(['rooms.room', 'travelers', 'bookable', 'partner', 'units', 'addons']);
        $isStay = $booking->service_type === ServiceType::Accommodation;
        $buildingId = $isStay ? $booking->rooms->first()?->room?->building_id : null;
        $product = ! $isStay && $booking->bookable !== null
            ? rescue(fn () => $this->admin->productOptions($booking->service_type === ServiceType::Package ? 'package' : 'trip', (string) $booking->bookable->slug), null, false)
            : null;

        return view('admin.bookings.edit', [
            'booking' => $booking,
            'isStay' => $isStay,
            'buildingRooms' => $buildingId !== null ? $this->admin->productOptions('building', (string) $buildingId)['rooms'] : [],
            'roomOptions' => $product['room_options'] ?? [],
            'product' => $product,
        ]);
    }

    public function preview(BookingChangeRequest $request, Booking $booking): JsonResponse
    {
        $preview = $this->modifications->preview($booking, BookingChangeData::fromRequest($request));

        return response()->json(['data' => $preview], $preview['valid'] ? 200 : 422);
    }

    public function update(BookingChangeRequest $request, Booking $booking): JsonResponse
    {
        $this->modifications->apply($booking, BookingChangeData::fromRequest($request), $request->user());

        return response()->json(['message' => 'Booking updated and price recalculated.', 'redirect' => route('admin.bookings.show', $booking)]);
    }

    public function releaseHold(Request $request, Booking $booking): RedirectResponse|JsonResponse
    {
        $this->bookings->releaseHold($booking, $request->user());

        return $request->expectsJson()
            ? response()->json(['message' => "Hold of {$booking->reference} released: the rooms are free again."])
            : redirect()->route('admin.bookings.show', $booking)->with('success', 'Hold released: the rooms are free again.');
    }
}
