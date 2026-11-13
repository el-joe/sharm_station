<?php

namespace App\DataTables;

use App\DataTables\Concerns\RendersRowActions;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentProof;
use App\Services\PaymentService;
use App\View\Components\Admin\StatusChip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

/**
 * Receipts review queue, payments log and payment methods.
 */
class PaymentDataTables
{
    use RendersRowActions;

    public function __construct(private readonly PaymentService $payments) {}

    public function proofs(?string $status): JsonResponse
    {
        return (new EloquentDataTable($this->payments->proofsQuery($status)))
            ->addColumn('booking', fn (PaymentProof $proof): string => $this->titleCell((string) $proof->booking?->reference, $proof->booking?->lead_name, route('admin.receipts.show', $proof)))
            ->addColumn('method', fn (PaymentProof $proof): string => e((string) ($proof->method?->name ?? '—')))
            ->editColumn('purpose', fn (PaymentProof $proof): string => e($proof->purpose->label()))
            ->editColumn('amount', fn (PaymentProof $proof): string => $proof->amount !== null ? e(number_format((float) $proof->amount, 2).' '.$proof->booking?->currency) : '—')
            ->addColumn('expected', fn (PaymentProof $proof): string => $this->money($proof->booking?->deposit_amount))
            ->editColumn('created_at', fn (PaymentProof $proof): string => e($proof->created_at->format('j M Y H:i')))
            ->editColumn('status', fn (PaymentProof $proof): string => StatusChip::html($proof->status))
            ->addColumn('actions', fn (PaymentProof $proof): string => $this->actions($this->editLink(route('admin.receipts.show', $proof), 'Review')))
            ->filterColumn('booking', fn (Builder $query, string $keyword) => $query->whereHas('booking', fn (Builder $booking) => $booking
                ->where('reference', 'like', "%{$keyword}%")->orWhere('lead_name', 'like', "%{$keyword}%")))
            ->rawColumns(['booking', 'method', 'purpose', 'amount', 'expected', 'created_at', 'status', 'actions'])
            ->toJson();
    }

    public function payments(?string $status): JsonResponse
    {
        return (new EloquentDataTable($this->payments->paymentsQuery($status)))
            ->addColumn('booking', fn (Payment $payment): string => $this->titleCell((string) $payment->booking?->reference, $payment->booking?->lead_name, $payment->booking ? route('admin.bookings.show', $payment->booking_id) : null))
            ->addColumn('method', fn (Payment $payment): string => e((string) ($payment->method?->name ?? 'Manual')))
            ->editColumn('type', fn (Payment $payment): string => e($payment->type->label()))
            ->editColumn('amount', fn (Payment $payment): string => $this->money($payment->amount).'<div class="text-xs text-ink-500">'.e(number_format((float) $payment->amount_in_currency, 2).' '.$payment->currency).'</div>')
            ->editColumn('status', fn (Payment $payment): string => StatusChip::html($payment->status))
            ->editColumn('paid_at', fn (Payment $payment): string => e((string) ($payment->paid_at?->format('j M Y H:i') ?? '—')))
            ->addColumn('recorded_by', fn (Payment $payment): string => e((string) ($payment->recorder?->name ?? ($payment->gateway_reference ? 'Gateway' : '—'))))
            ->filterColumn('booking', fn (Builder $query, string $keyword) => $query->whereHas('booking', fn (Builder $booking) => $booking->where('reference', 'like', "%{$keyword}%")))
            ->rawColumns(['booking', 'method', 'type', 'amount', 'status', 'paid_at', 'recorded_by'])
            ->toJson();
    }

    public function methods(): JsonResponse
    {
        return (new EloquentDataTable($this->payments->methodsQuery()))
            ->editColumn('name', fn (PaymentMethod $method): string => $this->titleCell((string) $method->name, $method->code, route('admin.payment-methods.edit', $method)))
            ->editColumn('type', fn (PaymentMethod $method): string => e($method->type->label().($method->driver ? ' · '.$method->driver : '')))
            ->editColumn('currencies', fn (PaymentMethod $method): string => e(implode(', ', $method->currencies ?? []) ?: 'All'))
            ->editColumn('proof_required', fn (PaymentMethod $method): string => $method->proof_required ? 'Yes' : 'No')
            ->editColumn('is_enabled', fn (PaymentMethod $method): string => StatusChip::html($method->is_enabled ? 'enabled' : 'disabled'))
            ->addColumn('actions', fn (PaymentMethod $method): string => $this->actions(
                $this->editLink(route('admin.payment-methods.edit', $method)),
                $this->deleteForm(route('admin.payment-methods.destroy', $method), 'Delete the payment method “'.$method->name.'”?'),
            ))
            ->filterColumn('name', fn (Builder $query, string $keyword) => $query->where('payment_methods.name->en', 'like', "%{$keyword}%"))
            ->orderColumn('name', fn (Builder $query, string $direction) => $query->orderBy('payment_methods.name->en', $direction))
            ->rawColumns(['name', 'type', 'currencies', 'proof_required', 'is_enabled', 'actions'])
            ->toJson();
    }
}
