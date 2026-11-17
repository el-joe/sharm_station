<?php

namespace App\Http\Middleware;

use App\Services\CurrencyService;
use App\Services\NationalityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies ?locale=en|it, ?currency=EUR|USD|GBP|EGP and ?nationality=XX (or the X-Locale / X-Currency /
 * X-Nationality headers, else the signed-in user's profile). Unknown nationalities price with the default group.
 *
 * Public endpoints always use customer prices, even for a partner token (CR-15): partner prices live
 * only under /partner/*.
 */
class SetLocaleAndCurrency
{
    public const LOCALES = ['en', 'it'];

    public function __construct(
        private readonly CurrencyService $currencies,
        private readonly NationalityService $nationalities,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        $locale = $request->query('locale') ?? $request->header('X-Locale') ?? $request->getPreferredLanguage(self::LOCALES);
        App::setLocale(in_array($locale, self::LOCALES, true) ? $locale : 'en');

        $currency = $request->query('currency') ?? $request->header('X-Currency') ?? $user?->currency ?? CurrencyService::BASE;
        $this->currencies->setCurrent(is_string($currency) ? $currency : CurrencyService::BASE);

        $nationality = $request->query('nationality') ?? $request->header('X-Nationality') ?? $user?->nationality_code;
        $this->nationalities->setCurrent(is_string($nationality) ? $nationality : null);

        return $next($request);
    }
}
