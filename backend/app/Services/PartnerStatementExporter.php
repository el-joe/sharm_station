<?php

namespace App\Services;

use App\Models\Partner;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Partner statement downloads (P1): PDF rendered from a Blade view with DomPDF, Excel with PhpSpreadsheet.
 */
class PartnerStatementExporter
{
    public const FORMATS = ['pdf', 'xlsx'];

    public function __construct(
        private readonly PartnerPortalService $portal,
        private readonly SettingsService $settings,
    ) {}

    public function download(Partner $partner, string $period, string $format): Response
    {
        $statement = $this->portal->statement($partner, $period);
        $filename = 'statement-'.$period.'-'.str($partner->company)->slug().'.'.$format;

        return $format === 'pdf' ? $this->pdf($partner, $statement, $filename) : $this->xlsx($partner, $statement, $filename);
    }

    /**
     * @param  array{summary: array<string, mixed>, bookings: list<array<string, mixed>>}  $statement
     */
    private function pdf(Partner $partner, array $statement, string $filename): Response
    {
        return Pdf::loadView('pdf.partner-statement', [
            'partner' => $partner,
            'summary' => $statement['summary'],
            'bookings' => $statement['bookings'],
            'company' => [
                'name' => $this->settings->get('site.name', config('app.name')),
                'address' => $this->settings->get('site.address'),
                'email' => $this->settings->get('site.email'),
                'phone' => $this->settings->get('site.phone'),
            ],
        ])->setOption(['enable_font_subsetting' => true, 'enable_remote' => false])->setPaper('a4', 'landscape')->download($filename);
    }

    /**
     * @param  array{summary: array<string, mixed>, bookings: list<array<string, mixed>>}  $statement
     */
    private function xlsx(Partner $partner, array $statement, string $filename): StreamedResponse
    {
        $summary = $statement['summary'];
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Statement '.$summary['period']);

        $sheet->fromArray([
            [$this->settings->get('site.name', config('app.name')).' · Partner statement'],
            ['Partner', $partner->company],
            ['Period', $summary['period_start'].' – '.$summary['period_end']],
            ['Status', ucfirst((string) $summary['status'])],
            [],
            ['Reference', 'Created', 'Service', 'Product', 'Service date', 'Lead client', 'Customer price (EUR)', 'Commission (EUR)', 'Partner total (EUR)', 'Paid (EUR)', 'Balance (EUR)', 'Status'],
        ], null, 'A1', true);

        $row = 7;

        foreach ($statement['bookings'] as $booking) {
            $sheet->fromArray([array_values($booking)], null, 'A'.$row, true);
            $row++;
        }

        $sheet->fromArray([['Totals', '', '', '', '', '', $summary['retail_total'], $summary['commission_amount'], $summary['partner_total'], $summary['paid'], $summary['balance'], '']], null, 'A'.$row, true);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A6:L6')->getFont()->setBold(true);
        $sheet->getStyle('A'.$row.':L'.$row)->getFont()->setBold(true);
        $sheet->getStyle('G7:K'.$row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
