<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\BookingDataTable;
use App\DTOs\Booking\BookingFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BalanceDueRequest;
use App\Http\Requests\Admin\BookingFilterRequest;
use App\Http\Requests\Admin\BookingStatusRequest;
use App\Models\Booking;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use App\Services\BookingService;
use App\Services\CancellationService;
use App\Services\Notifications\NotificationContextFactory;
use App\Services\PaymentService;
use App\Services\RefundService;
use App\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly PartnerRepositoryInterface $partners,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.bookings.index', [
            'partners' => $this->partners->options(),
            'initial' => $request->only(['status', 'payment_status', 'service_type', 'partner_id', 'cancellation']) + ['search' => $request->query('q')],
        ]);
    }

    public function data(BookingFilterRequest $request, BookingDataTable $table): JsonResponse
    {
        return $table->json(BookingFilterData::fromRequest($request));
    }

    public function export(BookingFilterRequest $request): StreamedResponse
    {
        $rows = $this->bookings->exportRows(BookingFilterData::fromRequest($request));
        $headings = $this->bookings->exportHeadings();

        return response()->streamDownload(function () use ($rows, $headings): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headings, escape: '');

            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($value) => is_string($value) && preg_match('/^[=+\-@]/', $value) ? "'".$value : $value, $row), escape: '');
            }

            fclose($handle);
        }, 'bookings-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(Booking $booking, PaymentService $payments, CancellationService $cancellations, RefundService $refunds, NotificationContextFactory $context): View
    {
        $booking = $this->bookings->loadForAdmin($booking);

        return view('admin.bookings.show', [
            'booking' => $booking,
            'refundable' => $refunds->refundable($booking),
            'refundableOnline' => $refunds->refundableOnline($booking),
            'balanceDueOn' => $context->balanceDueOn($booking),
            'paymentMethods' => $payments->methodOptions(),
            'refund' => $booking->cancellation_requested_at !== null ? $cancellations->suggestRefund($booking) : null,
        ]);
    }

    /**
     * Voucher PDF (?prices=0|1, ?locale=en|it; default: the booking language).
     */
    public function voucher(Request $request, Booking $booking, VoucherService $vouchers): Response
    {
        $withPrices = $request->query('prices', '1') !== '0';
        $locale = in_array($request->query('locale'), ['en', 'it'], true) ? (string) $request->query('locale') : null;

        return response($vouchers->render($booking, $withPrices, $locale), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$vouchers->filename($booking, $withPrices).'"',
        ]);
    }

    public function updateBalanceDue(BalanceDueRequest $request, Booking $booking): RedirectResponse
    {
        $this->bookings->setBalanceDueOn($booking, $request->validated('balance_due_on'), $request->user());

        return redirect()->route('admin.bookings.show', $booking)->with('success', 'Balance due date saved.');
    }

    public function updateStatus(BookingStatusRequest $request, Booking $booking): RedirectResponse
    {
        $this->bookings->changeStatus($booking, $request->status(), $request->user(), $request->validated('note'));

        return redirect()->route('admin.bookings.show', $booking)->with('success', "Status changed to {$request->status()->label()}.");
    }
}
