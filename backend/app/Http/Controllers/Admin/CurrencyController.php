<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CurrencyRatesRequest;
use App\Services\CurrencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CurrencyController extends Controller
{
    public function __construct(private readonly CurrencyService $currencies) {}

    public function index(): View
    {
        return view('admin.currencies.index', ['currencies' => $this->currencies->all()]);
    }

    public function update(CurrencyRatesRequest $request): RedirectResponse
    {
        $this->currencies->updateRates($request->rows());

        return redirect()->route('admin.currencies.index')->with('success', 'Currencies saved.');
    }
}
