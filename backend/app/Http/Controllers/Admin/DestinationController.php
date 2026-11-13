<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\DestinationDataTable;
use App\DTOs\Destination\DestinationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DestinationRequest;
use App\Models\Destination;
use App\Services\DestinationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DestinationController extends Controller
{
    public function __construct(private readonly DestinationService $destinations) {}

    public function index(): View
    {
        return view('admin.destinations.index');
    }

    public function data(DestinationDataTable $table): JsonResponse
    {
        return $table->json();
    }

    public function create(): View
    {
        return view('admin.destinations.form', ['destination' => new Destination(['is_active' => true])]);
    }

    public function store(DestinationRequest $request): RedirectResponse
    {
        $this->destinations->create(DestinationData::fromRequest($request));

        return redirect()->route('admin.destinations.index')->with('success', 'Destination created.');
    }

    public function edit(Destination $destination): View
    {
        return view('admin.destinations.form', ['destination' => $destination]);
    }

    public function update(DestinationRequest $request, Destination $destination): RedirectResponse
    {
        $this->destinations->update($destination, DestinationData::fromRequest($request));

        return redirect()->route('admin.destinations.index')->with('success', 'Destination saved.');
    }

    public function destroy(Destination $destination): RedirectResponse
    {
        $this->destinations->delete($destination);

        return redirect()->route('admin.destinations.index')->with('success', 'Destination deleted.');
    }
}
