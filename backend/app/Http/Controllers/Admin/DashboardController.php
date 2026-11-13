<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(): View
    {
        $finance = Gate::allows('manage-prices');

        return view('admin.dashboard', [
            'finance' => $finance,
            'kpis' => $finance ? $this->dashboard->kpis() : null,
            'chart' => $this->dashboard->chart(),
            'tasks' => $finance ? $this->dashboard->needsAttention() : [],
            'recent' => $finance ? $this->dashboard->recentBookings() : collect(),
        ]);
    }
}
