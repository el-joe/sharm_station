<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveCancellationRequest;
use App\Http\Requests\Admin\DeclineCancellationRequest;
use App\Models\Booking;
use App\Services\CancellationService;
use Illuminate\Http\RedirectResponse;

/**
 * Admin decision on a cancellation request (W4): approve with the refund to pay back, or decline with a reason.
 */
class BookingCancellationController extends Controller
{
    public function __construct(private readonly CancellationService $cancellations) {}

    public function approve(ApproveCancellationRequest $request, Booking $booking): RedirectResponse
    {
        $this->cancellations->approve($booking, $request->user(), (float) $request->validated('refund_amount'), $request->validated('note'));

        return redirect()->route('admin.bookings.show', $booking)->with('success', "Booking {$booking->reference} cancelled. Refund to pay: €".number_format((float) $booking->refund_amount, 2).'.');
    }

    public function decline(DeclineCancellationRequest $request, Booking $booking): RedirectResponse
    {
        $this->cancellations->decline($booking, $request->user(), $request->validated('decline_reason'));

        return redirect()->route('admin.bookings.show', $booking)->with('success', 'Cancellation request declined.');
    }
}
