{!! $company !!}

{!! $rendered->subject !!}

{!! $rendered->text !!}
@if ($hasVoucher)

{!! __('notifications.layout.voucher_attached', [], $mailLocale) !!}
@endif
@if ($rendered->actionUrl)

{!! $rendered->actionLabel !!}: {!! $rendered->actionUrl !!}
@endif

--
{!! __($footerKey, ['company' => $company], $mailLocale) !!}
