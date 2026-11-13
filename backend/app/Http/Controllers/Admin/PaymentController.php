<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\PaymentDataTables;
use App\DTOs\Payment\PaymentMethodData;
use App\DTOs\Payment\RecordPaymentData;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentRecordStatus;
use App\Enums\ProofStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentMethodRequest;
use App\Http\Requests\Admin\RecordPaymentRequest;
use App\Http\Requests\Admin\RejectProofRequest;
use App\Models\Booking;
use App\Models\PaymentMethod;
use App\Models\PaymentProof;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Receipts review, payments log, payment methods and payments recorded on a booking (SRS 10).
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaymentDataTables $tables,
    ) {}

    public function receipts(): View
    {
        return view('admin.payments.receipts', ['statuses' => ProofStatus::options()]);
    }

    public function receiptsData(Request $request): JsonResponse
    {
        return $this->tables->proofs(ProofStatus::tryFrom((string) $request->query('status', ProofStatus::Pending->value))?->value);
    }

    public function showReceipt(PaymentProof $proof): View
    {
        return view('admin.payments.receipt-show', ['proof' => $proof->load(['booking', 'method', 'reviewer'])]);
    }

    /**
     * Streams a proof file from the private disk (inline preview, ?download=1 to save it).
     */
    public function receiptFile(Request $request, PaymentProof $proof, int $index): BinaryFileResponse
    {
        $file = $this->payments->proofFile($proof, $index);
        abort_if($file === null, 404);

        $headers = ['Content-Type' => $file['mime'], 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'];

        return $request->boolean('download')
            ? response()->download($file['path'], $file['name'], $headers)
            : response()->file($file['path'], [...$headers, 'Content-Disposition' => 'inline; filename="'.addslashes($file['name']).'"']);
    }

    public function approve(Request $request, PaymentProof $proof): RedirectResponse
    {
        $this->payments->approveProof($proof, $request->user());

        return redirect()->route('admin.receipts.index')->with('success', 'Proof approved: payment recorded and booking confirmed.');
    }

    public function reject(RejectProofRequest $request, PaymentProof $proof): RedirectResponse
    {
        $this->payments->rejectProof($proof, $request->user(), $request->validated('rejection_reason'));

        return redirect()->route('admin.receipts.index')->with('success', 'Proof rejected. The customer can upload a new one.');
    }

    public function log(): View
    {
        return view('admin.payments.log', ['statuses' => PaymentRecordStatus::options()]);
    }

    public function logData(Request $request): JsonResponse
    {
        return $this->tables->payments(PaymentRecordStatus::tryFrom((string) $request->query('status'))?->value);
    }

    public function recordPayment(RecordPaymentRequest $request, Booking $booking): RedirectResponse
    {
        $this->payments->recordPayment($booking, RecordPaymentData::fromRequest($request), $request->user());

        return redirect()->route('admin.bookings.show', $booking)->with('success', 'Payment recorded.');
    }

    public function methods(): View
    {
        return view('admin.payments.methods', ['canPrice' => Gate::allows('manage-prices')]);
    }

    public function methodsData(): JsonResponse
    {
        return $this->tables->methods();
    }

    public function createMethod(PaymentGatewayManager $gateways): View
    {
        Gate::authorize('manage-prices');

        return view('admin.payments.method-form', ['method' => new PaymentMethod(['type' => PaymentMethodType::Manual, 'proof_required' => true, 'is_enabled' => true]), 'drivers' => $gateways->drivers()]);
    }

    public function storeMethod(PaymentMethodRequest $request): RedirectResponse
    {
        $this->payments->createMethod(PaymentMethodData::fromRequest($request));

        return redirect()->route('admin.payment-methods.index')->with('success', 'Payment method created.');
    }

    public function editMethod(PaymentMethod $paymentMethod, PaymentGatewayManager $gateways): View
    {
        Gate::authorize('manage-prices');

        return view('admin.payments.method-form', ['method' => $paymentMethod, 'drivers' => $gateways->drivers()]);
    }

    public function updateMethod(PaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->payments->updateMethod($paymentMethod, PaymentMethodData::fromRequest($request));

        return redirect()->route('admin.payment-methods.index')->with('success', 'Payment method saved.');
    }

    public function destroyMethod(PaymentMethod $paymentMethod): RedirectResponse
    {
        Gate::authorize('manage-prices');
        $this->payments->deleteMethod($paymentMethod);

        return redirect()->route('admin.payment-methods.index')->with('success', 'Payment method deleted.');
    }
}
