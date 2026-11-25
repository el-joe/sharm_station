<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\BookingVoucherRequest;
use App\Services\BookingService;
use App\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Voucher PDF of a booking (SRS 12.3) for the owner, the partner, staff or the booking-link holder (?token=).
 * Available once the deposit is paid. The end client of a partner booking always gets the voucher without prices.
 */
class BookingVoucherController extends ApiController
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly VoucherService $vouchers,
    ) {}

    public function __invoke(BookingVoucherRequest $request, string $reference): Response|JsonResponse
    {
        $booking = $this->bookings->findByReference($reference);
        $viewer = $request->user('sanctum');
        abort_if($booking === null || ! $this->bookings->canView($booking, $viewer, $request->validated('token')), 404, 'Booking not found.');

        if (! $this->vouchers->isAvailable($booking)) {
            return response()->json(['message' => 'The voucher is available once the deposit has been paid.'], 409);
        }

        $isPartnerViewer = $viewer !== null && ($viewer->isStaff() || ($booking->partner_id !== null && $viewer->partner?->id === $booking->partner_id));
        $withPrices = $request->withPrices() && ($booking->partner_id === null || $isPartnerViewer);

        return response($this->vouchers->render($booking, $withPrices, $request->validated('locale')), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->vouchers->filename($booking, $withPrices).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
