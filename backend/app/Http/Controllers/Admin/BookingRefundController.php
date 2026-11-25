<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\Payment\RecordRefundData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RecordRefundRequest;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Refunds on the booking detail (SRS 10.2, 12.4): record a full or partial refund, mark a pending manual refund as paid.
 */
class BookingRefundController extends Controller
{
    public function __construct(private readonly RefundService $refunds) {}

    public function store(RecordRefundRequest $request, Booking $booking): RedirectResponse
    {
        $created = $this->refunds->record($booking, RecordRefundData::fromRequest($request), $request->user());
        $total = array_sum(array_map(fn (Payment $payment): float => (float) $payment->amount, $created));

        return redirect()->route('admin.bookings.show', $booking)->with('success', 'Refund of €'.number_format($total, 2).' recorded.');
    }

    public function markPaid(Request $request, Booking $booking, Payment $payment): RedirectResponse
    {
        abort_unless($payment->booking_id === $booking->id, 404);
        $this->refunds->markPaid($payment, $request->user());

        return redirect()->route('admin.bookings.show', $booking)->with('success', 'Refund marked as paid.');
    }
}
