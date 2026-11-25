{{-- Branded notification layout: inline styles only (email clients), navy #101a5c / orange #ee8431. --}}
<!DOCTYPE html>
<html lang="{{ $mailLocale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $rendered->subject }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f1ec;font-family:Inter,Helvetica,Arial,sans-serif;color:#1b1f3b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1ec;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="background:#070a39;padding:22px 28px;">
                            <span style="display:inline-block;width:34px;height:34px;line-height:34px;border-radius:17px;background:#ee8431;color:#070a39;text-align:center;font-family:Georgia,serif;font-size:18px;font-weight:bold;vertical-align:middle;">S</span>
                            <span style="font-family:Georgia,serif;font-size:20px;color:#ffffff;vertical-align:middle;margin-left:8px;">{{ $company }}</span>
                            <div style="font-size:12px;color:#ee8431;margin-top:6px;letter-spacing:.04em;text-transform:uppercase;">{{ __('notifications.layout.tagline', [], $mailLocale) }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 28px 8px;font-size:15px;line-height:1.6;">
                            <h1 style="margin:0 0 18px;font-size:20px;line-height:1.3;color:#101a5c;">{{ $rendered->subject }}</h1>
                            {!! $rendered->html !!}
                            @if ($hasVoucher)
                                <p style="margin:0 0 16px;padding:10px 14px;background:#fdebdd;border-radius:8px;font-size:14px;">{{ __('notifications.layout.voucher_attached', [], $mailLocale) }}</p>
                            @endif
                        </td>
                    </tr>
                    @if ($rendered->actionUrl)
                        <tr>
                            <td style="padding:4px 28px 24px;">
                                <a href="{{ $rendered->actionUrl }}" style="display:inline-block;background:#c0550a;color:#ffffff;text-decoration:none;font-weight:600;padding:12px 22px;border-radius:8px;font-size:15px;">{{ $rendered->actionLabel }}</a>
                                <p style="margin:14px 0 0;font-size:12px;color:#6a6f8f;">{{ __('notifications.layout.button_fallback', [], $mailLocale) }}<br><a href="{{ $rendered->actionUrl }}" style="color:#101a5c;word-break:break-all;">{{ $rendered->actionUrl }}</a></p>
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:18px 28px;border-top:1px solid #e3e5f0;font-size:12px;color:#6a6f8f;">
                            {{ __($footerKey, ['company' => $company], $mailLocale) }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
