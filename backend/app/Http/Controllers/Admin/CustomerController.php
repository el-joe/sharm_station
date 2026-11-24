<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\CustomerDataTable;
use App\DTOs\Customer\CustomerFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerFilterRequest;
use App\Http\Requests\Admin\CustomerNotesRequest;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\NationalityGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Customers (A2): list, history, internal notes and activation.
 */
class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    public function index(NationalityGroupService $nationalities): View
    {
        return view('admin.customers.index', [
            'countries' => $nationalities->countries()->mapWithKeys(fn ($country): array => [$country->code => $country->code.' · '.$country->name])->all(),
        ]);
    }

    public function data(CustomerFilterRequest $request, CustomerDataTable $table): JsonResponse
    {
        return $table->json(CustomerFilterData::fromRequest($request));
    }

    public function show(User $customer): View
    {
        abort_unless($this->customers->isCustomer($customer), 404);

        return view('admin.customers.show', [
            'customer' => $this->customers->findForAdmin($customer),
            'bookings' => $this->customers->bookings($customer),
        ]);
    }

    public function updateNotes(CustomerNotesRequest $request, User $customer): RedirectResponse
    {
        abort_unless($this->customers->isCustomer($customer), 404);
        $this->customers->updateNotes($customer, $request->validated('admin_notes'), $request->user());

        return redirect()->route('admin.customers.show', $customer)->with('success', 'Notes saved.');
    }

    public function toggleActive(Request $request, User $customer): RedirectResponse
    {
        abort_unless($this->customers->isCustomer($customer), 404);
        $this->customers->setActive($customer, ! $customer->isActive(), $request->user());

        return redirect()->route('admin.customers.show', $customer)->with('success', $customer->isActive() ? 'Customer activated.' : 'Customer deactivated: they can no longer sign in.');
    }
}
