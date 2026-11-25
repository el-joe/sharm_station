<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Services\Notifications\TemplateRenderer;

/**
 * Website booking page the customer returns to after a hosted checkout:
 * {frontend}/{locale}/booking/{reference}?token=…&payment=success|cancel (config payments.return_url).
 */
final class ReturnUrls
{
    /**
     * @param  array<string, string>  $query
     */
    public static function page(Booking $booking, array $query = []): string
    {
        $url = str_replace(
            ['{locale}', '{reference}'],
            [TemplateRenderer::locale($booking->locale), $booking->reference],
            (string) config('payments.return_url'),
        );

        $query = ['token' => $booking->access_token, ...$query];

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query);
    }

    public static function success(Booking $booking): string
    {
        return self::page($booking, ['payment' => 'success']);
    }

    public static function cancel(Booking $booking): string
    {
        return self::page($booking, ['payment' => 'cancel']);
    }
}
