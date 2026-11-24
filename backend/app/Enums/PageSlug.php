<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Fixed static pages of the website; their texts are edited in Content > Pages.
 */
enum PageSlug: string
{
    use HasOptions;

    case About = 'about';
    case BookingTerms = 'booking-terms';
    case Privacy = 'privacy';
    case CancellationPolicy = 'cancellation-policy';
    case CookiePolicy = 'cookie-policy';

    public function label(): string
    {
        return match ($this) {
            self::About => 'About us',
            self::BookingTerms => 'Booking terms',
            self::Privacy => 'Privacy notice',
            self::CancellationPolicy => 'Cancellation policy',
            self::CookiePolicy => 'Cookie policy',
        };
    }
}
