<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\Report\ReportFilterData;
use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportFilterRequest;
use App\Services\ReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports (A6): super admins and agents.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(ReportFilterRequest $request): View
    {
        $filters = ReportFilterData::fromRequest($request);
        $tables = $this->reports->tables($filters);

        return view('admin.reports.index', [
            'filters' => $filters,
            'services' => ServiceType::options(),
            'kpis' => $this->reports->kpis($filters),
            'tables' => $tables,
            'charts' => $this->reports->charts($filters, $tables),
        ]);
    }

    public function export(ReportFilterRequest $request, string $table): StreamedResponse
    {
        abort_unless(array_key_exists($table, ReportService::TABLES), 404);
        $filters = ReportFilterData::fromRequest($request);
        $data = $this->reports->table($table, $filters);

        return response()->streamDownload(function () use ($data): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $data['headings'], escape: '');

            foreach ($data['rows'] as $row) {
                fputcsv($handle, array_map(fn ($value) => is_string($value) && preg_match('/^[=+\-@]/', $value) ? "'".$value : $value, $row), escape: '');
            }

            fclose($handle);
        }, "report-{$table}-{$filters->from->toDateString()}-{$filters->to->toDateString()}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
