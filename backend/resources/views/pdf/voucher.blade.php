@php($t = fn (string $key, array $replace = []) => __('voucher.'.$key, $replace, $locale))
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <title>{{ $t('title') }} {{ $booking->reference }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10.5px; color: #1b1f3b; margin: 0; }
        .band { background: #070a39; color: #fff; padding: 16px 20px; }
        .band h1 { margin: 0; font-size: 18px; }
        .band .ref { color: #ee8431; font-size: 22px; font-weight: bold; }
        .muted { color: #6a6f8f; }
        .wrap { padding: 16px 20px; }
        h2 { font-size: 12px; color: #101a5c; margin: 16px 0 6px; text-transform: uppercase; letter-spacing: .04em; }
        table.info { width: 100%; border-collapse: collapse; }
        table.info td { padding: 5px 6px; border-bottom: 1px solid #e3e5f0; vertical-align: top; }
        table.info td.label { width: 32%; color: #6a6f8f; }
        table.rows { width: 100%; border-collapse: collapse; }
        table.rows th { background: #101a5c; color: #fff; text-align: left; padding: 5px 6px; font-size: 9.5px; }
        table.rows td { border-bottom: 1px solid #e3e5f0; padding: 4px 6px; }
        .num { text-align: right; white-space: nowrap; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 8px; background: #fdebdd; color: #b2540f; font-weight: bold; }
        .totals td { font-size: 12px; }
        .footer { margin-top: 18px; padding-top: 10px; border-top: 2px solid #ee8431; }
    </style>
</head>
<body>
    <div class="band">
        <table style="width: 100%;">
            <tr>
                <td>
                    <h1>{{ $company['name'] }}</h1>
                    <div style="color: #ee8431;">{{ $t('title') }}</div>
                </td>
                <td style="text-align: right;">
                    <div class="muted" style="color: #c9cbe0;">{{ $t('reference') }}</div>
                    <div class="ref">{{ $booking->reference }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="wrap">
        <p>
            <span class="status">{{ $t('statuses.'.$booking->status->value) }}</span>
            <span class="muted">· {{ $t('issued') }} {{ $issuedOn }}</span>
        </p>

        <table class="info">
            <tr><td class="label">{{ $t('service') }}</td><td><strong>{{ $service }}</strong></td></tr>
            <tr><td class="label">{{ $t('date') }}</td><td>{{ $serviceDate }}</td></tr>
            @if ($booking->booking_type)
                <tr><td class="label">{{ $t('booking_type') }}</td><td>{{ $booking->booking_type->label() }}</td></tr>
            @endif
            <tr><td class="label">{{ $t('lead') }}</td><td>{{ $booking->lead_name }}@if ($booking->lead_phone) · {{ $booking->lead_phone }}@endif</td></tr>
            <tr><td class="label">{{ $t('party') }}</td><td>{{ $t('adults', ['count' => $booking->adults]) }}@if ($booking->children > 0), {{ $t('children', ['count' => $booking->children]) }}@endif</td></tr>
            @if ($meetingPoint)
                <tr><td class="label">{{ $t('meeting_point') }}</td><td>{{ $meetingPoint }}</td></tr>
            @endif
            @if ($booking->partner)
                <tr><td class="label">{{ $t('partner') }}</td><td>{{ $booking->partner->company }}</td></tr>
            @endif
            @if ($transferDetails !== '')
                <tr><td class="label">{{ $t('transfer') }}</td><td>{!! nl2br(e($transferDetails)) !!}</td></tr>
            @endif
            @if ($booking->notes)
                <tr><td class="label">{{ $t('notes') }}</td><td>{{ $booking->notes }}</td></tr>
            @endif
        </table>

        @if ($booking->units->isNotEmpty())
            <h2>{{ $t('units') }}</h2>
            <table class="rows">
                <tbody>
                    @foreach ($booking->units as $unit)
                        <tr><td>{{ $unit->quantity }} × {{ $unit->name }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($booking->rooms->isNotEmpty())
            <h2>{{ $t('rooms') }}</h2>
            <table class="rows">
                <tbody>
                    @foreach ($booking->rooms as $room)
                        <tr><td>{{ $room->room_name }}@if ($room->room_number) #{{ $room->room_number }}@endif</td><td class="num">{{ $t('nights', ['count' => $room->nights]) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($booking->addons->isNotEmpty())
            <h2>{{ $t('addons') }}</h2>
            <table class="rows">
                <tbody>
                    @foreach ($booking->addons as $addon)
                        <tr><td>{{ $addon->quantity }} × {{ $addon->name }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($booking->travelers->isNotEmpty())
            <h2>{{ $t('travellers') }}</h2>
            <table class="rows">
                <thead><tr><th>#</th><th>{{ $t('name') }}</th><th>{{ $t('type') }}</th><th>{{ $t('age') }}</th></tr></thead>
                <tbody>
                    @foreach ($booking->travelers as $traveler)
                        @php($type = $traveler->type instanceof \BackedEnum ? $traveler->type->value : (string) $traveler->type)
                        <tr><td>{{ $loop->iteration }}</td><td>{{ $traveler->first_name }} {{ $traveler->last_name }}</td><td>{{ $type === 'child' ? $t('child') : $t('adult') }}</td><td>{{ $traveler->age ?? '—' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($withPrices)
            <h2>{{ $t('prices') }}</h2>
            <table class="info totals">
                <tr><td class="label">{{ $t('total') }}</td><td class="num"><strong>{{ $prices['total'] }}</strong></td></tr>
                <tr><td class="label">{{ $t('paid') }}</td><td class="num">{{ $prices['paid'] }}</td></tr>
                <tr><td class="label">{{ $t('balance') }}</td><td class="num"><strong>{{ $prices['balance'] }}</strong></td></tr>
            </table>
            @if ($hasBalance)<p class="muted">{{ $t('balance_due', ['date' => $balanceDueOn]) }}</p>@endif
        @endif

        <div class="footer">
            <p>{{ $t('footer') }}</p>
            <p class="muted">{{ $t('contact') }}: {{ $company['email'] }}@if ($company['phone']) · {{ $company['phone'] }}@endif @if ($company['whatsapp']) · WhatsApp {{ $company['whatsapp'] }}@endif<br>{{ $company['address'] }}</p>
        </div>
    </div>
</body>
</html>
