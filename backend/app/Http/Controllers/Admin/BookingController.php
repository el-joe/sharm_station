<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\BookingDataTable;
use App\DTOs\Booking\BookingFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookingFilterRequest;
use App\Http\Requests\Admin\BookingStatusRequest;
use App\Models\Booking;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use App\Services\BookingService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'initial' => $request->only(['status', 'payment_status', 'service_type', 'partner_id']) + ['search' => $request->query('q')],
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

    public function show(Booking $booking, PaymentService $payments): View
    {
        return view('admin.bookings.show', [
            'booking' => $this->bookings->loadForAdmin($booking),
            'paymentMethods' => $payments->methodOptions(),
        ]);
    }

    public function updateStatus(BookingStatusRequest $request, Booking $booking): RedirectResponse
    {
        $this->bookings->changeStatus($booking, $request->status(), $request->user(), $request->validated('note'));

        return redirect()->route('admin.bookings.show', $booking)->with('success', "Status changed to {$request->status()->label()}.");
    }
}
