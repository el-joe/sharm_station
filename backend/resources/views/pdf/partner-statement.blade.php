@php
    $eur = fn ($amount) => '€ '.number_format((float) $amount, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Statement {{ $summary['period'] }} · {{ $partner->company }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #1b1f3b; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #101a5c; }
        .muted { color: #6a6f8f; }
        .head { width: 100%; margin-bottom: 16px; }
        .head td { vertical-align: top; }
        .kpis { width: 100%; border-collapse: collapse; margin: 12px 0 16px; }
        .kpis td { border: 1px solid #e3e5f0; padding: 8px; width: 20%; }
        .kpis strong { display: block; font-size: 13px; margin-top: 2px; }
        table.rows { width: 100%; border-collapse: collapse; }
        table.rows th { background: #101a5c; color: #fff; text-align: left; padding: 6px; font-size: 9px; }
        table.rows td { border-bottom: 1px solid #e3e5f0; padding: 5px 6px; }
        .num { text-align: right; white-space: nowrap; }
        tfoot td { font-weight: bold; border-top: 2px solid #101a5c; }
        .status { text-transform: uppercase; font-weight: bold; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <h1>Partner statement · {{ \Carbon\CarbonImmutable::parse($summary['period_start'])->format('F Y') }}</h1>
                <div><strong>{{ $partner->company }}</strong>@if ($partner->tax_number) · VAT {{ $partner->tax_number }}@endif</div>
                <div class="muted">{{ $summary['period_start'] }} – {{ $summary['period_end'] }} · status <span class="status">{{ $summary['status'] }}</span></div>
            </td>
            <td style="text-align: right;">
                <strong>{{ $company['name'] }}</strong><br>
                <span class="muted">{{ $company['address'] }}<br>{{ $company['email'] }} · {{ $company['phone'] }}</span>
            </td>
        </tr>
    </table>

    <table class="kpis">
        <tr>
            <td><span class="muted">Bookings</span><strong>{{ $summary['bookings_count'] }}</strong></td>
            <td><span class="muted">Customer prices</span><strong>{{ $eur($summary['retail_total']) }}</strong></td>
            <td><span class="muted">Your commission</span><strong>{{ $eur($summary['commission_amount']) }}</strong></td>
            <td><span class="muted">To pay (partner total)</span><strong>{{ $eur($summary['partner_total']) }}</strong></td>
            <td><span class="muted">Balance due</span><strong>{{ $eur($summary['balance']) }}</strong></td>
        </tr>
    </table>

    <table class="rows">
        <thead>
            <tr>
                <th>Reference</th><th>Created</th><th>Service</th><th>Product</th><th>Date</th><th>Lead client</th>
                <th class="num">Customer price</th><th class="num">Commission</th><th class="num">Partner total</th><th class="num">Paid</th><th class="num">Balance</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($bookings as $booking)
                <tr>
                    <td>{{ $booking['reference'] }}</td>
                    <td>{{ $booking['created_at'] }}</td>
                    <td>{{ $booking['service'] }}</td>
                    <td>{{ $booking['product'] }}</td>
                    <td>{{ $booking['service_date'] }}</td>
                    <td>{{ $booking['lead_name'] }}</td>
                    <td class="num">{{ $eur($booking['retail_total']) }}</td>
                    <td class="num">{{ $eur($booking['commission_amount']) }}</td>
                    <td class="num">{{ $eur($booking['partner_total']) }}</td>
                    <td class="num">{{ $eur($booking['paid']) }}</td>
                    <td class="num">{{ $eur($booking['balance']) }}</td>
                    <td>{{ $booking['status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="12" class="muted">No bookings in this period.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6">Totals</td>
                <td class="num">{{ $eur($summary['retail_total']) }}</td>
                <td class="num">{{ $eur($summary['commission_amount']) }}</td>
                <td class="num">{{ $eur($summary['partner_total']) }}</td>
                <td class="num">{{ $eur($summary['paid']) }}</td>
                <td class="num">{{ $eur($summary['balance']) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    <p class="muted" style="margin-top: 14px;">Amounts in EUR. Statements are due {{ \App\Services\PartnerPortalService::STATEMENT_DUE_DAYS }} days after the end of the month. Generated {{ now()->format('j M Y H:i') }}.</p>
</body>
</html>
