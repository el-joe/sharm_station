<?php

namespace App\DataTables;

use App\DataTables\Concerns\RendersRowActions;
use App\DTOs\Booking\BookingFilterData;
use App\Models\Booking;
use App\Services\BookingService;
use App\View\Components\Admin\StatusChip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

/**
 * Server-side bookings list. The free-text search is applied by the BookingFilterData
 * (reference, name, email, phone), so DataTables columns are not searchable themselves.
 */
class BookingDataTable
{
    use RendersRowActions;

    public function __construct(private readonly BookingService $bookings) {}

    public function json(BookingFilterData $filters): JsonResponse
    {
        return (new EloquentDataTable($this->bookings->adminQuery($filters)))
            ->editColumn('reference', fn (Booking $booking): string => '<a href="'.e(route('admin.bookings.show', $booking)).'" class="font-semibold text-primary-700 hover:underline">'.e($booking->reference).'</a>')
            ->editColumn('service_type', fn (Booking $booking): string => e($booking->service_type->label()).($booking->booking_type ? '<div class="text-xs text-ink-500">'.e($booking->booking_type->label()).'</div>' : ''))
            ->editColumn('lead_name', fn (Booking $booking): string => $this->titleCell($booking->lead_name, $booking->partner?->company ?? $booking->lead_phone))
            ->addColumn('service_date', fn (Booking $booking): string => e(($booking->start_date ?? $booking->check_in)?->format('j M Y') ?? '—'))
            ->editColumn('total', fn (Booking $booking): string => $this->money($booking->total))
            ->addColumn('paid', fn (Booking $booking): string => $this->money($booking->paidAmount()))
            ->addColumn('balance', fn (Booking $booking): string => $this->money($booking->dueAmount()))
            ->editColumn('status', fn (Booking $booking): string => StatusChip::html($booking->status))
            ->editColumn('payment_status', fn (Booking $booking): string => StatusChip::html($booking->payment_status))
            ->addColumn('actions', fn (Booking $booking): string => $this->actions($this->editLink(route('admin.bookings.show', $booking), 'Open')))
            ->orderColumn('service_date', fn (Builder $query, string $direction) => $query->orderByRaw('COALESCE(start_date, check_in) '.($direction === 'asc' ? 'asc' : 'desc')))
            ->rawColumns(['reference', 'service_type', 'lead_name', 'service_date', 'total', 'paid', 'balance', 'status', 'payment_status', 'actions'])
            ->toJson();
    }
}
